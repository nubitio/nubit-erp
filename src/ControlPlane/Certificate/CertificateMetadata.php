<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

final readonly class CertificateMetadata
{
    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $issuer
     */
    public function __construct(
        public ?string $subjectCn,
        public array $subject,
        public ?string $issuerCn,
        public array $issuer,
        public ?string $serialNumber,
        public string $fingerprintSha256,
        public \DateTimeImmutable $validFrom,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
