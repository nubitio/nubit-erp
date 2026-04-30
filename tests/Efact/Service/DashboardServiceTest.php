<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\ControlPlane\Dashboard\DashboardService;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DashboardServiceTest extends TestCase
{
    public function testGetTenantStatsReturnsCountsFromDatabase(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnMap([
            ['SELECT COUNT(*) FROM tenants', [], [], 12],
            ["SELECT COUNT(*) FROM tenants WHERE status = 'active'", [], [], 10],
            ["SELECT COUNT(*) FROM tenants WHERE status = 'suspended'", [], [], 1],
            ["SELECT COUNT(*) FROM tenants WHERE deleted_at IS NOT NULL", [], [], 1],
        ]);

        $service = $this->newService($connection);

        self::assertSame(
            ['total' => 12, 'active' => 10, 'suspended' => 1, 'deleted' => 1],
            $service->getTenantStats()
        );
    }

    public function testGetTenantStatsReturnsSafeDefaultsOnFailure(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = $this->newService($connection, $logger);

        self::assertSame(
            ['total' => 0, 'active' => 0, 'suspended' => 0, 'deleted' => 0],
            $service->getTenantStats()
        );
    }

    public function testGetPlanStatsReturnsValuesAndDefaultsToZeroOnError(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnMap([
            ['SELECT COUNT(*) FROM plans', [], [], 3],
            ["SELECT COUNT(*) FROM tenants WHERE plan IS NULL OR plan = ''", [], [], 2],
        ]);

        $service = $this->newService($connection);

        self::assertSame(
            ['total_plans' => 3, 'tenants_without_plan' => 2],
            $service->getPlanStats()
        );

        $failingConnection = $this->createStub(Connection::class);
        $failingConnection->method('fetchOne')->willThrowException(new \RuntimeException('boom'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $failingService = $this->newService($failingConnection, $logger);
        self::assertSame(
            ['total_plans' => 0, 'tenants_without_plan' => 0],
            $failingService->getPlanStats()
        );
    }

    public function testAuditAndBackupStatsReturnFallbacksWhenConnectionFails(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willThrowException(new \RuntimeException('schema error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $service = $this->newService($connection, $logger);

        self::assertSame([], $service->getRecentAuditSummary());
        self::assertSame(
            ['total_backups' => 0, 'last_backup_at' => null, 'total_size_bytes' => 0, 'failed_count' => 0],
            $service->getBackupStats()
        );
    }

    private function newService(Connection $connection, ?LoggerInterface $logger = null): DashboardService
    {
        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry->method('getConnection')->with('control_plane')->willReturn($connection);

        return new DashboardService(
            $connectionRegistry,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
