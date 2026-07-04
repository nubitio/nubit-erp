<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificatePresenter;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\TenantCertificate;
use PHPUnit\Framework\TestCase;

final class CertificatePresenterTest extends TestCase
{
    public function testToArraySerializesOperationalMetadata(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01 00:00:00');
        $expiresAt = new \DateTimeImmutable('2026-01-01 00:00:00');
        $uploadedAt = new \DateTimeImmutable('2025-01-02 08:00:00');

        $certificate = new TenantCertificate(
            id: 7,
            tenantId: 3,
            tenantName: 'acme',
            ruc: '20123456789',
            status: CertificateStatus::Active,
            subjectCn: 'ACME SAC',
            issuerCn: 'ECEP-RENIEC',
            serialNumber: 'ABC123',
            fingerprintSha256: str_repeat('a', 64),
            validFrom: $validFrom,
            expiresAt: $expiresAt,
            uploadedAt: $uploadedAt,
            uploadedBy: 'admin',
            revokedAt: null,
            revokedBy: null,
            revocationReason: null,
            supersededById: null,
            lastAlertedAt: null,
            lycetSyncStatus: 'synced',
        );

        $payload = CertificatePresenter::toArray($certificate);

        $this->assertSame(7, $payload['id']);
        $this->assertSame('20123456789', $payload['ruc']);
        $this->assertSame('active', $payload['status']);
        $this->assertSame('ACME SAC', $payload['subjectCn']);
        $this->assertSame($validFrom->format(DATE_ATOM), $payload['validFrom']);
        $this->assertSame('synced', $payload['lycetSyncStatus']);
    }
}