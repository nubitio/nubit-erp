<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

final readonly class TenantCertificate
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $tenantName,
        public string $ruc,
        public CertificateStatus $status,
        public ?string $subjectCn,
        public ?string $issuerCn,
        public ?string $serialNumber,
        public string $fingerprintSha256,
        public \DateTimeImmutable $validFrom,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $uploadedAt,
        public ?string $uploadedBy,
        public ?\DateTimeImmutable $revokedAt,
        public ?string $revokedBy,
        public ?string $revocationReason,
        public ?int $supersededById,
        public ?\DateTimeImmutable $lastAlertedAt,
        public string $lycetSyncStatus = 'synced',
        public ?string $lycetSyncError = null,
        public ?\DateTimeImmutable $lycetSyncUpdatedAt = null,
    ) {
    }
}
