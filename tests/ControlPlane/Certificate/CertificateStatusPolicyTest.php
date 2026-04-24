<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\CertificateStatusPolicy;
use PHPUnit\Framework\TestCase;

final class CertificateStatusPolicyTest extends TestCase
{
    public function testReturnsExpiredWhenCertificateIsPastExpiry(): void
    {
        $policy = new CertificateStatusPolicy(30);

        self::assertSame(
            CertificateStatus::Expired,
            $policy->statusFor(new \DateTimeImmutable('2026-04-01'), new \DateTimeImmutable('2026-04-24'))
        );
    }

    public function testReturnsExpiringSoonWithinThirtyDays(): void
    {
        $policy = new CertificateStatusPolicy(30);

        self::assertSame(
            CertificateStatus::ExpiringSoon,
            $policy->statusFor(new \DateTimeImmutable('2026-05-20'), new \DateTimeImmutable('2026-04-24'))
        );
    }

    public function testReturnsActiveOutsideThirtyDays(): void
    {
        $policy = new CertificateStatusPolicy(30);

        self::assertSame(
            CertificateStatus::Active,
            $policy->statusFor(new \DateTimeImmutable('2026-06-24'), new \DateTimeImmutable('2026-04-24'))
        );
    }
}
