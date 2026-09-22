<?php

declare(strict_types=1);

namespace App\Settings\Dto;

final readonly class TenantCertificateView
{
    public function __construct(
        public int $id,
        public string $ruc,
        public string $status,
        public ?string $subjectCn,
        public ?string $issuerCn,
        public ?string $serialNumber,
        public string $fingerprintSha256,
        public \DateTimeImmutable $validFrom,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $uploadedAt,
        public ?\DateTimeImmutable $revokedAt,
        public string $lycetSyncStatus,
    ) {}
}
