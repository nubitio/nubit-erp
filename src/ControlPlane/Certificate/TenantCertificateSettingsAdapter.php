<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

use App\Settings\Contract\TenantCertificateServiceInterface;
use App\Settings\Dto\TenantCertificateView;

/** Adapts the control-plane certificate lifecycle to the tenant settings port. */
final readonly class TenantCertificateSettingsAdapter implements TenantCertificateServiceInterface
{
    public function __construct(
        private CertificateLifecycleServiceInterface $lifecycle,
    ) {}

    public function upsert(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        ?string $uploadedBy = null,
        ?callable $beforeCommit = null,
    ): TenantCertificateView {
        return $this->view($this->lifecycle->upsert(
            $tenantName,
            $ruc,
            $solUser,
            $solPass,
            $certPem,
            $uploadedBy,
            $beforeCommit,
        ));
    }

    public function list(string $tenantName): array
    {
        return array_map($this->view(...), $this->lifecycle->list($tenantName));
    }

    private function view(TenantCertificate $certificate): TenantCertificateView
    {
        return new TenantCertificateView(
            id: $certificate->id,
            ruc: $certificate->ruc,
            status: $certificate->status->value,
            subjectCn: $certificate->subjectCn,
            issuerCn: $certificate->issuerCn,
            serialNumber: $certificate->serialNumber,
            fingerprintSha256: $certificate->fingerprintSha256,
            validFrom: $certificate->validFrom,
            expiresAt: $certificate->expiresAt,
            uploadedAt: $certificate->uploadedAt,
            revokedAt: $certificate->revokedAt,
            lycetSyncStatus: $certificate->lycetSyncStatus,
        );
    }
}
