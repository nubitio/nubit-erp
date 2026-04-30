<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateMetadata;
use App\ControlPlane\Certificate\CertificateStatus;
use PHPUnit\Framework\TestCase;

final class CertificateDomainTest extends TestCase
{
    // ── CertificateStatus ─────────────────────────────────────────────────────

    public function testCertificateStatusValues(): void
    {
        self::assertSame('active', CertificateStatus::Active->value);
        self::assertSame('expiring_soon', CertificateStatus::ExpiringSoon->value);
        self::assertSame('expired', CertificateStatus::Expired->value);
        self::assertSame('revoked', CertificateStatus::Revoked->value);
        self::assertSame('superseded', CertificateStatus::Superseded->value);
    }

    public function testCertificateStatusFromValue(): void
    {
        self::assertSame(CertificateStatus::Active, CertificateStatus::from('active'));
        self::assertSame(CertificateStatus::Expired, CertificateStatus::from('expired'));
    }

    // ── CertificateMetadata ───────────────────────────────────────────────────

    public function testCertificateMetadataHoldsFields(): void
    {
        $validFrom = new \DateTimeImmutable('2026-01-01');
        $expiresAt = new \DateTimeImmutable('2027-01-01');

        $meta = new CertificateMetadata(
            subjectCn: 'Mi Empresa SAC',
            subject: ['CN' => 'Mi Empresa SAC', 'O' => 'Mi Empresa SAC'],
            issuerCn: 'SUNAT CA',
            issuer: ['CN' => 'SUNAT CA'],
            serialNumber: '0123456789ABCDEF',
            fingerprintSha256: 'abc123',
            validFrom: $validFrom,
            expiresAt: $expiresAt,
        );

        self::assertSame('Mi Empresa SAC', $meta->subjectCn);
        self::assertSame('SUNAT CA', $meta->issuerCn);
        self::assertSame('0123456789ABCDEF', $meta->serialNumber);
        self::assertSame('abc123', $meta->fingerprintSha256);
        self::assertSame($validFrom, $meta->validFrom);
        self::assertSame($expiresAt, $meta->expiresAt);
    }

    public function testCertificateMetadataWithNullableFields(): void
    {
        $meta = new CertificateMetadata(
            subjectCn: null,
            subject: [],
            issuerCn: null,
            issuer: [],
            serialNumber: null,
            fingerprintSha256: 'fp',
            validFrom: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable(),
        );

        self::assertNull($meta->subjectCn);
        self::assertNull($meta->issuerCn);
        self::assertNull($meta->serialNumber);
    }
}
