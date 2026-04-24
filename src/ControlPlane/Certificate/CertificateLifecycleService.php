<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use App\Core\Platform\Tenant\TenantRegistryInterface;
use Doctrine\Persistence\ConnectionRegistry;

final readonly class CertificateLifecycleService
{
    public function __construct(
        private TenantRegistryInterface $tenantRegistry,
        private TenantCertificateRepository $repository,
        private CertificateMetadataExtractor $metadataExtractor,
        private CertificateStatusPolicy $statusPolicy,
        private ElectronicBillingCompanyPortInterface $electronicBillingCompany,
        private ConnectionRegistry $connectionRegistry,
    ) {
    }

    public function upsert(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        ?string $uploadedBy = null,
    ): TenantCertificate {
        $tenant = $this->requireTenant($tenantName);
        $metadata = $this->metadataExtractor->extract($certPem);
        $status = $this->statusPolicy->statusFor($metadata->expiresAt);

        $connection = $this->connectionRegistry->getConnection('control_plane');
        $connection->beginTransaction();
        try {
            $previous = $this->repository->findActiveByTenantAndRuc((int)$tenant['id'], $ruc);
            $id = $this->repository->insert((int)$tenant['id'], (string)$tenant['name'], $ruc, $status, $metadata, $uploadedBy);
            if ($previous !== null) {
                $this->repository->markSuperseded($previous->id, $id);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        // Lycet remains the signing/storage backend. Keep it outside the DB transaction
        // to avoid holding database locks during network IO.
        $this->electronicBillingCompany->upsertCompany($ruc, $solUser, $solPass, $certPem);

        $certificate = $this->repository->findActiveByTenantAndRuc((int)$tenant['id'], $ruc);
        if ($certificate === null) {
            throw new \RuntimeException('Certificate metadata was not found after upsert.');
        }

        return $certificate;
    }

    public function revoke(string $tenantName, string $ruc, string $revokedBy, string $reason): void
    {
        $tenant = $this->requireTenant($tenantName);
        $certificate = $this->repository->findActiveByTenantAndRuc((int)$tenant['id'], $ruc);
        if ($certificate === null) {
            throw new \RuntimeException(sprintf('No active certificate found for RUC %s.', $ruc));
        }

        $this->repository->revoke($certificate->id, $revokedBy, $reason);
        $this->electronicBillingCompany->removeCompany($ruc);
    }

    /**
     * @return list<TenantCertificate>
     */
    public function list(string $tenantName): array
    {
        $tenant = $this->requireTenant($tenantName);

        return $this->repository->listByTenant((int)$tenant['id']);
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
