<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

final readonly class CertificatePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(TenantCertificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'ruc' => $certificate->ruc,
            'status' => $certificate->status->value,
            'subjectCn' => $certificate->subjectCn,
            'issuerCn' => $certificate->issuerCn,
            'serialNumber' => $certificate->serialNumber,
            'fingerprintSha256' => $certificate->fingerprintSha256,
            'validFrom' => $certificate->validFrom->format(DATE_ATOM),
            'expiresAt' => $certificate->expiresAt->format(DATE_ATOM),
            'uploadedAt' => $certificate->uploadedAt->format(DATE_ATOM),
            'uploadedBy' => $certificate->uploadedBy,
            'revokedAt' => $certificate->revokedAt?->format(DATE_ATOM),
            'revokedBy' => $certificate->revokedBy,
            'revocationReason' => $certificate->revocationReason,
            'supersededById' => $certificate->supersededById,
            'lastAlertedAt' => $certificate->lastAlertedAt?->format(DATE_ATOM),
            'lycetSyncStatus' => $certificate->lycetSyncStatus,
            'lycetSyncError' => $certificate->lycetSyncError,
            'lycetSyncUpdatedAt' => $certificate->lycetSyncUpdatedAt?->format(DATE_ATOM),
        ];
    }
}