<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

interface CertificateLifecycleServiceInterface
{
    public function upsert(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        ?string $uploadedBy = null,
    ): TenantCertificate;

    public function revoke(string $tenantName, string $ruc, string $revokedBy, string $reason): void;

    public function retryUpsertSync(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
    ): TenantCertificate;

    public function retryRevocation(string $tenantName, string $ruc): void;

    public function detail(string $tenantName, int $certificateId): TenantCertificate;

    /**
     * @return list<TenantCertificate>
     */
    public function list(string $tenantName): array;

    /**
     * @return list<TenantCertificate>
     */
    public function refreshExpiringStatuses(int $days = 30): array;

    /**
     * @return list<TenantCertificate>
     */
    public function findStuckLycetSyncs(int $minutesThreshold = 30): array;
}
