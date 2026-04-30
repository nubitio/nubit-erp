<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\ControlPlane\Tenant\Service\TenantNotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantNotificationServiceTest extends TestCase
{
    public function testGetTenantTargetsReturnsDecodedRows(): void
    {
        $connection = $this->readyConnection();
        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'event_key' => TenantNotificationService::EVENT_SUNAT_PAUSED,
                    'channel' => 'email',
                    'is_enabled' => 1,
                    'recipients_json' => '["admin@example.com"," admin@example.com "]',
                    'config_json' => '{"template":"x"}',
                ],
            ]
        );

        $service = $this->serviceWithConnection($connection);
        $rows = $service->getTenantTargets(12);

        self::assertCount(1, $rows);
        self::assertSame(['admin@example.com'], $rows[0]['recipients']);
        self::assertSame(['template' => 'x'], $rows[0]['config']);
        self::assertTrue($rows[0]['is_enabled']);
    }

    public function testSaveTenantTargetsInsertsOnlySupportedEventAndChannel(): void
    {
        $connection = $this->readyConnection();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $connection->expects(self::once())->method('executeStatement');

        $service = $this->serviceWithConnection($connection);
        $service->saveTenantTargets(7, [
            TenantNotificationService::EVENT_SUNAT_PAUSED => [
                'email' => [
                    'enabled' => true,
                    'severity' => 'critical',
                    'recipients' => ['one@example.com', ''],
                    'config' => ['source' => 'tests'],
                ],
                'invalid' => [
                    'enabled' => true,
                    'severity' => 'critical',
                    'recipients' => ['x'],
                    'config' => [],
                ],
            ],
            'invalid-event' => [
                'email' => [
                    'enabled' => true,
                    'severity' => 'critical',
                    'recipients' => ['x'],
                    'config' => [],
                ],
            ],
        ]);
    }

    public function testDispatchForTenantNameSendsEmailAndRecordsDelivery(): void
    {
        $connection = $this->readyConnection();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $connection->method('fetchAssociative')->willReturn(
            [
                'id' => 9,
                'name' => 'acme',
            ]
        );
        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'channel' => 'email',
                    'severity' => 'critical',
                    'recipients_json' => '["ops@example.com"]',
                    'config_json' => '{}',
                ],
            ]
        );
        $connection->expects(self::once())->method('insert')->with(
            'tenant_notification_deliveries',
            self::callback(static fn (array $row): bool => ($row['status'] ?? '') === 'sent')
        );

        $service = $this->serviceWithConnection($connection, $mailer);
        $sent = $service->dispatchForTenantName(
            tenantName: 'acme',
            eventKey: TenantNotificationService::EVENT_SUNAT_PAUSED,
            subject: 'Test',
            message: 'Mensaje'
        );

        self::assertSame(1, $sent);
    }

    public function testDispatchForTenantNameReturnsZeroWhenTenantDoesNotExist(): void
    {
        $connection = $this->readyConnection();
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::never())->method('insert');

        $service = $this->serviceWithConnection($connection);
        $sent = $service->dispatchForTenantName(
            tenantName: 'missing',
            eventKey: TenantNotificationService::EVENT_SUNAT_PAUSED,
            subject: 'Test',
            message: 'Mensaje'
        );

        self::assertSame(0, $sent);
    }

    public function testDispatchTestTargetByTenantIdValidatesInputs(): void
    {
        $service = $this->serviceWithConnection($this->readyConnection());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported event key');
        $service->dispatchTestTargetByTenantId(1, 'invalid-event', 'email', 'acme');
    }

    public function testDispatchTestTargetByTenantIdReturnsFalseWhenTargetMissingOrDisabled(): void
    {
        $connection = $this->readyConnection();
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            false,
            ['is_enabled' => 0, 'recipients_json' => '[]', 'config_json' => '{}', 'severity' => 'info']
        );
        $connection->expects(self::never())->method('insert');

        $service = $this->serviceWithConnection($connection);
        self::assertFalse($service->dispatchTestTargetByTenantId(
            2,
            TenantNotificationService::EVENT_SUNAT_PAUSED,
            'email',
            'acme'
        ));
        self::assertFalse($service->dispatchTestTargetByTenantId(
            2,
            TenantNotificationService::EVENT_SUNAT_PAUSED,
            'email',
            'acme'
        ));
    }

    public function testDispatchTestTargetByTenantIdSendsEmailWhenEnabled(): void
    {
        $connection = $this->readyConnection();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $connection->method('fetchAssociative')->willReturn(
            [
                'is_enabled' => 1,
                'severity' => 'critical',
                'recipients_json' => '["ops@example.com"]',
                'config_json' => '{}',
            ]
        );
        $connection->expects(self::once())->method('insert')->with(
            'tenant_notification_deliveries',
            self::callback(static fn (array $row): bool => ($row['status'] ?? '') === 'sent')
        );

        $service = $this->serviceWithConnection($connection, $mailer);
        $sent = $service->dispatchTestTargetByTenantId(
            4,
            TenantNotificationService::EVENT_SUNAT_PAUSED,
            'email',
            'acme'
        );

        self::assertTrue($sent);
    }

    public function testGetTenantTargetsReturnsEmptyWhenControlPlaneNotReady(): void
    {
        $connection = $this->readyConnection(false);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $service = $this->serviceWithConnection($connection);
        self::assertSame([], $service->getTenantTargets(10));
    }

    private function serviceWithConnection(
        Connection $connection,
        ?MailerInterface $mailer = null,
    ): TenantNotificationService {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return new TenantNotificationService(
            $registry,
            $mailer ?? $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @return MockObject&Connection
     */
    private function readyConnection(bool $ready = true): Connection
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn($ready);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        return $connection;
    }
}
