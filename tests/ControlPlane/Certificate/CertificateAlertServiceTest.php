<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateAlertService;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\CertificateStatusPolicy;
use App\ControlPlane\Certificate\TenantCertificate;
use App\ControlPlane\Certificate\TenantCertificateRepositoryInterface;
use App\Core\Platform\Tenant\TenantConnectionSwitcherInterface;
use App\Identity\Notification\NotificationType;
use App\Identity\Notification\UserNotificationServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Email;

final class CertificateAlertServiceTest extends TestCase
{
    public function testSendExpiringAlertsNotifiesAdminsAndPublishesMercure(): void
    {
        $certificate = $this->certificate(
            id: 42,
            status: CertificateStatus::ExpiringSoon,
            expiresAt: new \DateTimeImmutable('+15 days'),
            lastAlertedAt: null,
        );

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findExpiringWithin')->with(30)->willReturn([$certificate]);
        $repository->expects(self::once())->method('refreshStatus')->with(42, CertificateStatus::ExpiringSoon);
        $repository->expects(self::once())->method('markAlerted')->with(42);

        $tenantSwitcher = $this->createMock(TenantConnectionSwitcherInterface::class);
        $tenantSwitcher->expects(self::once())->method('switchConnection')->with('acme');

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['username' => 'admin@acme.com'],
            ['username' => 'notanemail'],
        ]);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::isInstanceOf(Email::class));

        $userNotificationService = $this->createMock(UserNotificationServiceInterface::class);
        $userNotificationService->expects(self::once())
            ->method('notifyByRolesDeduped')
            ->with(
                ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
                NotificationType::CERTIFICATE_EXPIRING,
                'warning',
                self::stringContains('20123456789'),
                self::anything(),
                self::stringContains('/admin/tenants/'),
                86400,
            );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (Update $update): bool {
                self::assertStringContainsString('tenant/acme/notifications', $update->getTopics()[0]);
                $payload = json_decode($update->getData(), true);
                self::assertSame(NotificationType::CERTIFICATE_EXPIRING, $payload['type']);
                self::assertSame('acme', $payload['tenant']);
                self::assertSame('20123456789', $payload['ruc']);

                return true;
            }));

        $service = new CertificateAlertService(
            $repository,
            new CertificateStatusPolicy(30),
            $tenantSwitcher,
            $connectionRegistry,
            $userNotificationService,
            $mailer,
            $hub,
            new NullLogger(),
        );

        self::assertSame(1, $service->sendExpiringAlerts(30));
    }

    public function testSendExpiringAlertsSkipsCertificateAlertedWithinLast24Hours(): void
    {
        $certificate = $this->certificate(
            id: 43,
            status: CertificateStatus::ExpiringSoon,
            expiresAt: new \DateTimeImmutable('+15 days'),
            lastAlertedAt: new \DateTimeImmutable('-1 hour'),
        );

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findExpiringWithin')->with(30)->willReturn([$certificate]);
        $repository->expects(self::never())->method('markAlerted');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $service = new CertificateAlertService(
            $repository,
            new CertificateStatusPolicy(30),
            $this->createStub(TenantConnectionSwitcherInterface::class),
            $this->createStub(ConnectionRegistry::class),
            $this->createStub(UserNotificationServiceInterface::class),
            $mailer,
            $hub,
            new NullLogger(),
        );

        self::assertSame(0, $service->sendExpiringAlerts(30));
    }

    public function testSendExpiringAlertsContinuesOnFailureAndLogsError(): void
    {
        $cert1 = $this->certificate(id: 1, status: CertificateStatus::ExpiringSoon, expiresAt: new \DateTimeImmutable('+10 days'), lastAlertedAt: null);
        $cert2 = $this->certificate(id: 2, status: CertificateStatus::ExpiringSoon, expiresAt: new \DateTimeImmutable('+20 days'), lastAlertedAt: null, tenantName: 'beta', tenantId: 2);

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findExpiringWithin')->willReturn([$cert1, $cert2]);
        $repository->method('refreshStatus');
        // Only cert2 should be marked alerted (cert1 throws)
        $repository->expects(self::once())->method('markAlerted')->with(2);

        $tenantSwitcher = $this->createStub(TenantConnectionSwitcherInterface::class);
        $tenantSwitcher->method('switchConnection')
            ->willReturnCallback(function (string $name): void {
                if ($name === 'acme') {
                    throw new \RuntimeException('Connection failed');
                }
            });

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['username' => 'admin@beta.com']]);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish');

        $service = new CertificateAlertService(
            $repository,
            new CertificateStatusPolicy(30),
            $tenantSwitcher,
            $connectionRegistry,
            $this->createStub(UserNotificationServiceInterface::class),
            $mailer,
            $hub,
            new NullLogger(),
        );

        // cert1 fails, cert2 succeeds → 1 sent
        self::assertSame(1, $service->sendExpiringAlerts(30));
    }

    public function testSendExpiringAlertsMercureFailureDoesNotPreventMarkAlerted(): void
    {
        $certificate = $this->certificate(
            id: 99,
            status: CertificateStatus::ExpiringSoon,
            expiresAt: new \DateTimeImmutable('+5 days'),
            lastAlertedAt: null,
        );

        $repository = $this->createMock(TenantCertificateRepositoryInterface::class);
        $repository->method('findExpiringWithin')->willReturn([$certificate]);
        $repository->method('refreshStatus');
        $repository->expects(self::once())->method('markAlerted')->with(99);

        $tenantSwitcher = $this->createStub(TenantConnectionSwitcherInterface::class);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $connectionRegistry = $this->createStub(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->willReturn($connection);

        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('Mercure down'));

        $service = new CertificateAlertService(
            $repository,
            new CertificateStatusPolicy(30),
            $tenantSwitcher,
            $connectionRegistry,
            $this->createStub(UserNotificationServiceInterface::class),
            $this->createStub(MailerInterface::class),
            $hub,
            new NullLogger(),
        );

        self::assertSame(1, $service->sendExpiringAlerts(30));
    }

    private function certificate(
        int $id,
        CertificateStatus $status,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $lastAlertedAt,
        string $tenantName = 'acme',
        int $tenantId = 1,
    ): TenantCertificate {
        return new TenantCertificate(
            id: $id,
            tenantId: $tenantId,
            tenantName: $tenantName,
            ruc: '20123456789',
            status: $status,
            subjectCn: 'Test Cert',
            issuerCn: 'Test CA',
            serialNumber: '01',
            fingerprintSha256: str_repeat('a', 64),
            validFrom: new \DateTimeImmutable('2026-01-01'),
            expiresAt: $expiresAt,
            uploadedAt: new \DateTimeImmutable('2026-04-24'),
            uploadedBy: 'ops@example.com',
            revokedAt: null,
            revokedBy: null,
            revocationReason: null,
            supersededById: null,
            lastAlertedAt: $lastAlertedAt,
        );
    }
}
