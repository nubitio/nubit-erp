<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateLifecycleService;
use App\ControlPlane\Certificate\CertificateMetadataExtractor;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\CertificateStatusPolicy;
use App\ControlPlane\Certificate\TenantCertificate;
use App\ControlPlane\Certificate\TenantCertificateRepositoryInterface;
use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CertificateLifecycleServiceTest extends TestCase
{
    public function testUpsertStoresMetadataSupersedesPreviousAndUpdatesLycet(): void
    {
        $pem = $this->createSelfSignedCertificatePem('Acme Cert');
        $previous = $this->certificate(id: 10, status: CertificateStatus::Active);
        $current = $this->certificate(id: 11, status: CertificateStatus::Active);

        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->expects(self::exactly(2))
            ->method('findActiveByTenantAndRuc')
            ->with(1, '20123456789')
            ->willReturnOnConsecutiveCalls($previous, $current);
        $repository->expects(self::once())
            ->method('insert')
            ->with(
                1,
                'acme',
                '20123456789',
                self::isInstanceOf(CertificateStatus::class),
                self::anything(),
                'ops@example.com',
            )
            ->willReturn(11);
        $repository->expects(self::once())->method('markSuperseded')->with(10, 11);

        $billing = $this->createMock(ElectronicBillingCompanyPortInterface::class);
        $billing->expects(self::once())
            ->method('upsertCompany')
            ->with('20123456789', '20123456789MODDATOS', 'secret', $pem);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $registry,
        );

        self::assertSame($current, $service->upsert(
            'acme',
            '20123456789',
            '20123456789MODDATOS',
            'secret',
            $pem,
            'ops@example.com',
        ));
    }

    public function testRevokeMarksMetadataAndRemovesCompanyFromLycet(): void
    {
        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findActiveByTenantAndRuc')
            ->with(1, '20123456789')
            ->willReturn($this->certificate(id: 10, status: CertificateStatus::Active));
        $repository->expects(self::once())->method('revoke')->with(10, 'ops@example.com', 'compromised');
        $repository->expects(self::once())->method('markRevocationPending')->with(10, 'ops@example.com', 'compromised');

        $billing = $this->createMock(ElectronicBillingCompanyPortInterface::class);
        $billing->expects(self::once())->method('removeCompany')->with('20123456789');

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $this->createMock(ConnectionRegistry::class),
        );

        $service->revoke('acme', '20123456789', 'ops@example.com', 'compromised');
    }

    public function testUpsertFailureLeavesRecoverableLycetFailureState(): void
    {
        $pem = $this->createSelfSignedCertificatePem('Acme Cert');

        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->expects(self::once())->method('findActiveByTenantAndRuc')->willReturn(null);
        $repository->expects(self::once())->method('insert')->willReturn(11);
        $repository->expects(self::once())->method('markLycetSyncFailed')->with(11, 'upsert_failed', 'lycet unavailable');
        $repository->expects(self::never())->method('markSuperseded');

        $billing = $this->createStub(ElectronicBillingCompanyPortInterface::class);
        $billing->method('upsertCompany')->willThrowException(new \RuntimeException('lycet unavailable'));

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $registry,
        );

        $this->expectExceptionMessage('lycet unavailable');
        $service->upsert('acme', '20123456789', '20123456789MODDATOS', 'secret', $pem, 'ops@example.com');
    }

    public function testRevokeFailureDoesNotMarkCertificateRevoked(): void
    {
        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findActiveByTenantAndRuc')->with(1, '20123456789')->willReturn($this->certificate(id: 10, status: CertificateStatus::Active));
        $repository->expects(self::once())->method('markRevocationPending')->with(10, 'ops@example.com', 'compromised');
        $repository->expects(self::once())->method('markLycetSyncFailed')->with(10, 'revocation_failed', 'lycet unavailable');
        $repository->expects(self::never())->method('revoke');

        $billing = $this->createStub(ElectronicBillingCompanyPortInterface::class);
        $billing->method('removeCompany')->willThrowException(new \RuntimeException('lycet unavailable'));

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $this->createMock(ConnectionRegistry::class),
        );

        $this->expectExceptionMessage('lycet unavailable');
        $service->revoke('acme', '20123456789', 'ops@example.com', 'compromised');
    }

    public function testRetryRevocationCompletesPendingRemove(): void
    {
        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $certificate = $this->certificate(id: 10, status: CertificateStatus::Active);
        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findActiveByTenantAndRuc')->with(1, '20123456789')->willReturn($certificate);
        $repository->expects(self::once())->method('markLycetSyncPending')->with(10, 'revocation_pending');
        $repository->expects(self::once())->method('revoke')->with(10, 'ops@example.com', 'rotated');

        $billing = $this->createMock(ElectronicBillingCompanyPortInterface::class);
        $billing->expects(self::once())->method('removeCompany')->with('20123456789');

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $this->createMock(ConnectionRegistry::class),
        );

        $service->retryRevocation('acme', '20123456789');
    }

    public function testRetryUpsertCompletesPendingLycetSyncAndSupersedesOtherActiveCertificates(): void
    {
        $pem = $this->createSelfSignedCertificatePem('Acme Retry Cert');

        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $certificate = $this->certificate(id: 11, status: CertificateStatus::Active);
        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByTenantAndFingerprint')
            ->with(1, self::isString())
            ->willReturn($certificate);
        $repository->expects(self::once())->method('markLycetSyncPending')->with(11, 'upsert_pending');
        $repository->expects(self::once())->method('markLycetSynced')->with(11);
        $repository->expects(self::once())->method('markOtherActiveCertificatesSuperseded')->with(1, '20123456789', 11);
        $repository->expects(self::once())->method('findByTenantAndId')->with(1, 11)->willReturn($certificate);

        $billing = $this->createMock(ElectronicBillingCompanyPortInterface::class);
        $billing->expects(self::once())
            ->method('upsertCompany')
            ->with('20123456789', '20123456789MODDATOS', 'secret', $pem);

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $this->createMock(ConnectionRegistry::class),
        );

        self::assertSame($certificate, $service->retryUpsertSync('acme', '20123456789', '20123456789MODDATOS', 'secret', $pem));
    }

    public function testRetryUpsertFailureKeepsRecoverableFailureState(): void
    {
        $pem = $this->createSelfSignedCertificatePem('Acme Retry Cert');

        $tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $tenantRegistry->method('getTenantByName')->with('acme')->willReturn([
            'id' => 1,
            'name' => 'acme',
        ]);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findByTenantAndFingerprint')->with(1, self::isString())->willReturn($this->certificate(id: 11, status: CertificateStatus::Active));
        $repository->expects(self::once())->method('markLycetSyncPending')->with(11, 'upsert_pending');
        $repository->expects(self::once())->method('markLycetSyncFailed')->with(11, 'upsert_failed', 'lycet still unavailable');
        $repository->expects(self::never())->method('markLycetSynced');
        $repository->expects(self::never())->method('markOtherActiveCertificatesSuperseded');

        $billing = $this->createStub(ElectronicBillingCompanyPortInterface::class);
        $billing->method('upsertCompany')->willThrowException(new \RuntimeException('lycet still unavailable'));

        $service = new CertificateLifecycleService(
            $tenantRegistry,
            $repository,
            new CertificateMetadataExtractor(),
            new CertificateStatusPolicy(30),
            $billing,
            $this->createMock(ConnectionRegistry::class),
        );

        $this->expectExceptionMessage('lycet still unavailable');
        $service->retryUpsertSync('acme', '20123456789', '20123456789MODDATOS', 'secret', $pem);
    }

    private function certificate(int $id, CertificateStatus $status): TenantCertificate
    {
        return new TenantCertificate(
            id: $id,
            tenantId: 1,
            tenantName: 'acme',
            ruc: '20123456789',
            status: $status,
            subjectCn: 'Acme Cert',
            issuerCn: 'Acme Cert',
            serialNumber: '01',
            fingerprintSha256: str_repeat('a', 64),
            validFrom: new \DateTimeImmutable('2026-01-01'),
            expiresAt: new \DateTimeImmutable('2027-01-01'),
            uploadedAt: new \DateTimeImmutable('2026-04-24'),
            uploadedBy: 'ops@example.com',
            revokedAt: null,
            revokedBy: 'ops@example.com',
            revocationReason: 'rotated',
            supersededById: null,
            lastAlertedAt: null,
        );
    }

    private function createSelfSignedCertificatePem(string $commonName): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey);

        $csr = openssl_csr_new(['commonName' => $commonName], $privateKey);
        self::assertNotFalse($csr);

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365);
        self::assertNotFalse($certificate);

        $exported = openssl_x509_export($certificate, $pem);
        self::assertTrue($exported);

        return $pem;
    }
}
