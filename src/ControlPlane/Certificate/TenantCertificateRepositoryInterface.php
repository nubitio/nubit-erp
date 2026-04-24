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

    /**
     * @return list<TenantCertificate>
     */
    public function findExpiringWithin(int $days): array;

    public function insert(
        int $tenantId,
        string $tenantName,
        string $ruc,
        CertificateStatus $status,
        CertificateMetadata $metadata,
        ?string $uploadedBy,
    ): int;

    public function markSuperseded(int $id, int $supersededById): void;

    public function revoke(int $id, string $revokedBy, string $reason): void;

    public function refreshStatus(int $id, CertificateStatus $status): void;

    public function markAlerted(int $id): void;
}
