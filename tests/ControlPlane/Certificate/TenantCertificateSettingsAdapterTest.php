<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\TenantCertificate;
use App\ControlPlane\Certificate\TenantCertificateSettingsAdapter;
use App\Settings\Dto\TenantCertificateView;
use PHPUnit\Framework\TestCase;

final class TenantCertificateSettingsAdapterTest extends TestCase
{
    public function testUpsertMapsControlPlaneModelToTenantViewAndPreservesCallback(): void
    {
        $beforeCommit = static function (): void {};
        $certificate = $this->certificate();
        $lifecycle = $this->createMock(CertificateLifecycleServiceInterface::class);
        $lifecycle
            ->expects(self::once())
            ->method('upsert')
            ->with('tenant-a', '20123456789', 'sol-user', 'sol-pass', 'pem', 'admin', $beforeCommit)
            ->willReturn($certificate);

        $view = new TenantCertificateSettingsAdapter($lifecycle)->upsert(
            'tenant-a',
            '20123456789',
            'sol-user',
            'sol-pass',
            'pem',
            'admin',
            $beforeCommit,
        );

        self::assertSame(7, $view->id);
        self::assertSame('active', $view->status);
        self::assertSame('fingerprint', $view->fingerprintSha256);
        self::assertSame('pending', $view->lycetSyncStatus);
    }

    public function testListMapsAllCertificatesWithoutLeakingControlPlaneType(): void
    {
        $lifecycle = $this->createMock(CertificateLifecycleServiceInterface::class);
        $lifecycle->method('list')->with('tenant-a')->willReturn([$this->certificate()]);

        $views = new TenantCertificateSettingsAdapter($lifecycle)->list('tenant-a');

        self::assertCount(1, $views);
        self::assertSame('20123456789', $views[0]->ruc);
        self::assertInstanceOf(TenantCertificateView::class, $views[0]);
    }

    private function certificate(): TenantCertificate
    {
        return new TenantCertificate(
            id: 7,
            tenantId: 11,
            tenantName: 'tenant-a',
            ruc: '20123456789',
            status: CertificateStatus::Active,
            subjectCn: 'tenant-a',
            issuerCn: 'SUNAT',
            serialNumber: 'serial',
            fingerprintSha256: 'fingerprint',
            validFrom: new \DateTimeImmutable('2026-01-01'),
            expiresAt: new \DateTimeImmutable('2027-01-01'),
            uploadedAt: new \DateTimeImmutable('2026-01-01'),
            uploadedBy: 'admin',
            revokedAt: null,
            revokedBy: null,
            revocationReason: null,
            supersededById: null,
            lastAlertedAt: null,
            lycetSyncStatus: 'pending',
        );
    }
}
