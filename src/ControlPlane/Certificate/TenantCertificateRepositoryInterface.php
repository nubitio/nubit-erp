<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

interface TenantCertificateRepositoryInterface
{
    /**
     * @return list<TenantCertificate>
     */
    public function listByTenant(int $tenantId): array;

    public function findActiveByTenantAndRuc(int $tenantId, string $ruc): ?TenantCertificate;

    public function findByTenantAndId(int $tenantId, int $id): ?TenantCertificate;

    public function findByTenantAndFingerprint(int $tenantId, string $fingerprintSha256): ?TenantCertificate;

    /**
     * @return list<TenantCertificate>
     */
    public function findExpiringWithin(int $days): array;

    /**
     * Certificates stuck in a non-"synced" Lycet status for at least
     * $minutesThreshold minutes (upsert_pending, upsert_failed,
     * revocation_pending, revocation_failed) — candidates for
     * app:control-plane:certificate:retry-sync or manual investigation.
     *
     * @return list<TenantCertificate>
     */
    public function findStuckLycetSyncs(int $minutesThreshold): array;

    public function insert(
        int $tenantId,
        string $tenantName,
        string $ruc,
        CertificateStatus $status,
        CertificateMetadata $metadata,
        ?string $uploadedBy,
    ): int;

    public function markSuperseded(int $id, int $supersededById): void;

    public function markOtherActiveCertificatesSuperseded(int $tenantId, string $ruc, int $supersededById): void;

    public function markLycetSyncPending(int $id, string $status): void;

    public function markLycetSyncFailed(int $id, string $status, string $error): void;

    public function markLycetSynced(int $id): void;

    public function markRevocationPending(int $id, string $revokedBy, string $reason): void;

    public function revoke(int $id, string $revokedBy, string $reason): void;

    public function refreshStatus(int $id, CertificateStatus $status): void;

    public function markAlerted(int $id): void;
}
