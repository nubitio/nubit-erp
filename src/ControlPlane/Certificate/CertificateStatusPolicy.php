<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

final readonly class CertificateStatusPolicy
{
    public function __construct(
        private int $expiringSoonDays = 30,
    ) {}

    public function statusFor(\DateTimeImmutable $expiresAt, ?\DateTimeImmutable $now = null): CertificateStatus
    {
        $now ??= new \DateTimeImmutable();
        if ($expiresAt <= $now) {
            return CertificateStatus::Expired;
        }

        if ($expiresAt <= $now->modify(sprintf('+%d days', $this->expiringSoonDays))) {
            return CertificateStatus::ExpiringSoon;
        }

        return CertificateStatus::Active;
    }
}
