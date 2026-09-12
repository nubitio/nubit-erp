<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;

final readonly class CertificateLifecycleService implements CertificateLifecycleServiceInterface
{
    public function __construct(
        private TenantRegistryInterface $tenantRegistry,
        private TenantCertificateRepositoryInterface $repository,
        private CertificateMetadataExtractor $metadataExtractor,
        private CertificateStatusPolicy $statusPolicy,
        private ElectronicBillingCompanyPortInterface $electronicBillingCompany,
        private ConnectionRegistry $connectionRegistry,
    ) {}

    public function upsert(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        ?string $uploadedBy = null,
        ?callable $beforeCommit = null,
    ): TenantCertificate {
        $tenant = $this->requireTenant($tenantName);
        $metadata = $this->metadataExtractor->extract($certPem);
        $status = $this->statusPolicy->statusFor($metadata->expiresAt);

        $connection = $this->connectionRegistry->getConnection('control_plane');
        $connection->beginTransaction();
        try {
            $previous = $this->repository->findActiveByTenantAndRuc((int) $tenant['id'], $ruc);
            $id = $this->repository->insert(
                (int) $tenant['id'],
                (string) $tenant['name'],
                $ruc,
                $status,
                $metadata,
                $uploadedBy,
            );
            if ($beforeCommit !== null) {
                $beforeCommit($id, $metadata, $tenant);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        try {
            // Lycet remains the signing/storage backend. Keep it outside the DB transaction
            // to avoid holding database locks during network IO. The DB row is left
            // upsert_pending/upsert_failed until Lycet confirms the write.
            $this->electronicBillingCompany->upsertCompany($ruc, $solUser, $solPass, $certPem);
            $this->repository->markLycetSynced($id);
            if ($previous !== null) {
                $this->repository->markSuperseded($previous->id, $id);
            }
        } catch (\Throwable $e) {
            $this->repository->markLycetSyncFailed($id, 'upsert_failed', $e->getMessage());
            throw $e;
        }

        $certificate = $this->repository->findActiveByTenantAndRuc((int) $tenant['id'], $ruc);
        if ($certificate === null) {
            throw new \RuntimeException('Certificate metadata was not found after upsert.');
        }

        return $certificate;
    }

    public function revoke(string $tenantName, string $ruc, string $revokedBy, string $reason): void
    {
        $tenant = $this->requireTenant($tenantName);
        $certificate = $this->repository->findActiveByTenantAndRuc((int) $tenant['id'], $ruc);
        if ($certificate === null) {
            throw new \RuntimeException(sprintf('No active certificate found for RUC %s.', $ruc));
        }

        $this->repository->markRevocationPending($certificate->id, $revokedBy, $reason);
        try {
            $this->electronicBillingCompany->removeCompany($ruc);
        } catch (\Throwable $e) {
            $this->repository->markLycetSyncFailed($certificate->id, 'revocation_failed', $e->getMessage());
            throw $e;
        }
        $this->repository->revoke($certificate->id, $revokedBy, $reason);
    }

    public function retryUpsertSync(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
    ): TenantCertificate {
        $tenant = $this->requireTenant($tenantName);
        $metadata = $this->metadataExtractor->extract($certPem);
        $certificate = $this->repository->findByTenantAndFingerprint((int) $tenant['id'], $metadata->fingerprintSha256);
        if ($certificate === null || $certificate->ruc !== $ruc) {
            throw new \RuntimeException(sprintf(
                'No recoverable certificate upsert found for RUC %s and the supplied certificate.',
                $ruc,
            ));
        }

        $this->repository->markLycetSyncPending($certificate->id, 'upsert_pending');
        try {
            $this->electronicBillingCompany->upsertCompany($ruc, $solUser, $solPass, $certPem);
        } catch (\Throwable $e) {
            $this->repository->markLycetSyncFailed($certificate->id, 'upsert_failed', $e->getMessage());
            throw $e;
        }
        $this->repository->markLycetSynced($certificate->id);
        $this->repository->markOtherActiveCertificatesSuperseded((int) $tenant['id'], $ruc, $certificate->id);

        return $this->repository->findByTenantAndId((int) $tenant['id'], $certificate->id) ?? $certificate;
    }

    public function retryRevocation(string $tenantName, string $ruc): void
    {
        $tenant = $this->requireTenant($tenantName);
        $certificate = $this->repository->findActiveByTenantAndRuc((int) $tenant['id'], $ruc);
        if ($certificate === null) {
            throw new \RuntimeException(sprintf('No recoverable revocation found for RUC %s.', $ruc));
        }

        $revokedBy = $certificate->revokedBy ?? 'retry';
        $reason = $certificate->revocationReason ?? 'retry';
        $this->repository->markLycetSyncPending($certificate->id, 'revocation_pending');
        try {
            $this->electronicBillingCompany->removeCompany($ruc);
        } catch (\Throwable $e) {
            $this->repository->markLycetSyncFailed($certificate->id, 'revocation_failed', $e->getMessage());
            throw $e;
        }
        $this->repository->revoke($certificate->id, $revokedBy, $reason);
    }

    public function detail(string $tenantName, int $certificateId): TenantCertificate
    {
        $tenant = $this->requireTenant($tenantName);
        $certificate = $this->repository->findByTenantAndId((int) $tenant['id'], $certificateId);
        if ($certificate === null) {
            throw new \RuntimeException(sprintf('Certificate "%d" not found.', $certificateId));
        }

        return $certificate;
    }

    /**
     * @return list<TenantCertificate>
     */
    public function list(string $tenantName): array
    {
        $tenant = $this->requireTenant($tenantName);

        return $this->repository->listByTenant((int) $tenant['id']);
    }

    /**
     * @return list<TenantCertificate>
     */
    public function refreshExpiringStatuses(int $days = 30): array
    {
        $certificates = $this->repository->findExpiringWithin($days);
        foreach ($certificates as $certificate) {
            $status = $this->statusPolicy->statusFor($certificate->expiresAt);
            if ($status !== $certificate->status) {
                $this->repository->refreshStatus($certificate->id, $status);
            }
        }

        return $certificates;
    }

    /**
     * @return list<TenantCertificate>
     */
    public function findStuckLycetSyncs(int $minutesThreshold = 30): array
    {
        return $this->repository->findStuckLycetSyncs($minutesThreshold);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireTenant(string $tenantName): array
    {
        $tenant = $this->tenantRegistry->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found.', $tenantName));
        }

        if (!isset($tenant['id'], $tenant['name'])) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" is missing id/name metadata.', $tenantName));
        }

        return $tenant;
    }
}
