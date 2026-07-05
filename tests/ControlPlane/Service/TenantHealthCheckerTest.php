<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Service;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantHealthChecker;
use App\Efact\Doctrine\DBAL\TenantConnection;
use App\Tests\ControlPlane\Support\TenantServiceFactory;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantHealthCheckerTest extends TestCase
{
    public function testCheckReturnsHealthyWhenTenantConnectionAndSchemaAreValid(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['user', 'tenant_settings', 'branch']);

        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection->expects(self::once())->method('executeQuery')->with('SELECT 1');
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantServiceWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $this->createMock(LoggerInterface::class),
        );

        $result = $checker->check('acme');

        self::assertTrue($result['healthy']);
        self::assertTrue($result['checks']['control_plane']['ok']);
        self::assertTrue($result['checks']['connection']['ok']);
        self::assertTrue($result['checks']['schema']['ok']);
    }

    public function testCheckReturnsUnhealthyWhenTenantDoesNotExist(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::never())->method('executeQuery');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantServiceWithConfig("[]\n"),
            $this->createMock(LoggerInterface::class),
        );

        $result = $checker->check('missing');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['control_plane']['ok']);
        self::assertFalse($result['checks']['connection']['ok']);
        self::assertArrayNotHasKey('schema', $result['checks']);
    }

    public function testCheckReturnsUnhealthyWhenConnectionFails(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection->method('executeQuery')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantServiceWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $logger,
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['connection']['ok']);
        self::assertArrayNotHasKey('schema', $result['checks']);
    }

    public function testCheckReportsMissingSchemaTables(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['user', 'branch']);

        $tenantConnection = $this->createStub(TenantConnection::class);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantServiceWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $this->createMock(LoggerInterface::class),
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['schema']['ok']);
        self::assertStringContainsString('tenant_settings', $result['checks']['schema']['message']);
    }

    public function testCheckHandlesControlPlaneLookupException(): void
    {
        $tenantConnection = $this->createStub(TenantConnection::class);
        $tenantConnection->method('executeQuery')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantServiceWithConfig("[invalid-yaml\n"),
            $logger,
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertSame('Control plane lookup failed', $result['checks']['control_plane']['message']);
        self::assertSame('Database connection failed', $result['checks']['connection']['message']);
    }

    private function tenantRegistry(TenantConnection $tenantConnection): ConnectionRegistry
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('tenant')->willReturn($tenantConnection);

        return $registry;
    }

    private function tenantServiceWithConfig(string $yamlContent): TenantService
    {
        $path = sys_get_temp_dir() . '/efact-tenant-health-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($path, $yamlContent);

        return TenantServiceFactory::yaml($path);
    }

}
