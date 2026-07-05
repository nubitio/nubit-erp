<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Service;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use App\ControlPlane\Tenant\Service\TenantUsageService;
use App\Efact\Doctrine\DBAL\TenantConnection;
use App\Tests\ControlPlane\Support\TenantServiceFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantUsageServiceTest extends TestCase
{
    public function testCollectMetricsReturnsCountsForExistingTables(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection
            ->method('quoteSingleIdentifier')
            ->willReturnCallback(static fn(string $table): string => sprintf('`%s`', $table));

        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema
            ->method('listTableNames')
            ->willReturn(['usuario', 'cliente', 'producto', 'tax_document', 'local', 'cash_sessions']);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $tenantConnection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int|string {
                return match (true) {
                    str_contains($sql, '`usuario`') => 11,
                    str_contains($sql, '`cliente`') => 22,
                    str_contains($sql, '`producto`') => 33,
                    str_contains($sql, '`tax_document`') => 44,
                    str_contains($sql, '`local`') => 2,
                    str_contains($sql, '`cash_sessions`') => 3,
                    str_contains($sql, 'information_schema.tables') => '4096',
                    default => 0,
                };
            });

        $service = $this->newService(
            $this->tenantServiceWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $tenantConnection,
        );

        self::assertSame(
            [
                'users' => 11,
                'customers' => 22,
                'products' => 33,
                'invoices' => 44,
                'branches' => 2,
                'cash_shifts' => 3,
                'storage_bytes' => 4096,
            ],
            $service->collectMetrics('acme'),
        );
    }

    public function testCollectMetricsHandlesMissingTablesAndCountFailures(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection->method('quoteSingleIdentifier')->willReturn('`usuario`');

        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['usuario']);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $tenantConnection->method('fetchOne')->willThrowException(new \RuntimeException('db error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $service = $this->newService(
            $this->tenantServiceWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $tenantConnection,
            null,
            $logger,
        );

        self::assertSame(
            [
                'users' => -1,
                'customers' => 0,
                'products' => 0,
                'invoices' => 0,
                'branches' => 0,
                'cash_shifts' => 0,
                'storage_bytes' => -1,
            ],
            $service->collectMetrics('acme'),
        );
    }

    public function testStoreMetricsSkipsWhenTenantIsMissingOrIdIsZero(): void
    {
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane->expects(self::never())->method('executeStatement');

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::never())->method('getConnection');

        $missingTenantService = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("[]\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );
        $missingTenantService->storeMetrics('missing', ['users' => 1]);

        $idZeroService = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("- { id: 0, name: acme, connection: acme_db }\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );
        $idZeroService->storeMetrics('acme', ['users' => 1]);
    }

    public function testStoreMetricsPersistsEachMetricForValidTenant(): void
    {
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane->expects(self::exactly(2))->method('executeStatement');

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($controlPlane);

        $service = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("- { id: 9, name: acme, connection: acme_db }\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );

        $service->storeMetrics('acme', ['users' => 12, 'storage_bytes' => 2048]);
    }

    public function testGetLatestMetricsAndRecentHistoryReadFromControlPlane(): void
    {
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([
                ['metric_name' => 'users', 'metric_value' => 12],
                ['metric_name' => 'customers', 'metric_value' => 5],
            ], [
                ['metric_name' => 'users', 'collected_at' => '2026-02-28 12:00:00', 'metric_value' => 12],
            ]);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($controlPlane);

        $service = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("- { id: 9, name: acme, connection: acme_db }\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(['users' => 12, 'customers' => 5], $service->getLatestMetrics('acme'));
        self::assertSame(
            [['metric_name' => 'users', 'collected_at' => '2026-02-28 12:00:00', 'metric_value' => 12]],
            $service->getRecentHistory('acme', 0, 7000),
        );
    }

    public function testGetUsageDeltasCalculatesDifferencesPerMetric(): void
    {
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane
            ->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls([
                ['metric_name' => 'users', 'metric_value' => 10],
                ['metric_name' => 'customers', 'metric_value' => 4],
            ], [
                ['metric_name' => 'users', 'metric_value' => 14],
                ['metric_name' => 'customers', 'metric_value' => 4],
            ]);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($controlPlane);

        $service = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("- { id: 9, name: acme, connection: acme_db }\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(
            [
                'users' => ['previous' => 10, 'current' => 14, 'delta' => 4],
                'customers' => ['previous' => 4, 'current' => 4, 'delta' => 0],
            ],
            $service->getUsageDeltas('acme'),
        );
    }

    public function testMetricReadersThrowWhenTenantDoesNotExist(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $service = new TenantUsageService(
            $registry,
            $this->tenantServiceWithConfig("[]\n"),
            new TenantResourceCatalog(),
            $this->createMock(LoggerInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant "missing" not found');

        $service->getLatestMetrics('missing');
    }

    private function newService(
        TenantService $tenantService,
        TenantConnection $tenantConnection,
        ?Connection $controlPlaneConnection = null,
        ?LoggerInterface $logger = null,
    ): TenantUsageService {
        $controlPlaneConnection ??= $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry
            ->method('getConnection')
            ->willReturnCallback(static function (string $name) use (
                $tenantConnection,
                $controlPlaneConnection,
            ): Connection {
                return $name === 'tenant' ? $tenantConnection : $controlPlaneConnection;
            });

        return new TenantUsageService(
            $registry,
            $tenantService,
            new TenantResourceCatalog(),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    private function tenantServiceWithConfig(string $yamlContent): TenantService
    {
        $path = sys_get_temp_dir() . '/efact-tenant-usage-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($path, $yamlContent);

        return TenantServiceFactory::yaml($path);
    }
}
