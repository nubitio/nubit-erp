<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\ControlPlane\Tenant\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Extended test coverage for TenantManager lifecycle methods:
 * changePlan, addTenantDomain (duplicate), getTenantDomains,
 * requireControlPlaneReady (exception path), YAML fallback RuntimeException,
 * getTenant DB path, getTenantByName DB path, getTenantByDomain DB path,
 * updateBackupFrequency / updateBackupRetention (success + tenant-not-found),
 * and suspendTenant / restoreTenant cache-invalidation paths.
 */
final class TenantManagerLifecycleTest extends TestCase
{
    // -------------------------------------------------------------------------
    // changePlan — success path
    // -------------------------------------------------------------------------

    public function testChangePlanSuccessUpdatesFieldAndInvalidatesCache(): void
    {
        $connection = $this->dbConnection(true);

        // fetchOne calls:
        //   1. planExists check   → plan row found (non-false)
        //   2. getTenant SELECT   → tenant row (for cache invalidation)
        //   3. getTenantDomains fetchAllAssociative → []  (inside invalidateTenantDomainCaches via getTenantDomains)
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls('starter', ['id' => 5, 'name' => 'acme', 'connection' => 'default']);

        $connection->method('fetchAssociative')
            ->willReturn(['id' => 5, 'name' => 'acme', 'connection' => 'default', 'status' => 'active', 'plan' => 'starter']);

        $connection->method('fetchAllAssociative')->willReturn([]);

        $connection->expects(self::once())
            ->method('update')
            ->with(
                'tenants',
                self::callback(static fn (array $data): bool => $data['plan'] === 'starter'),
                ['id' => 5],
            )
            ->willReturn(1);

        $manager = $this->dbManager($connection);
        $manager->changePlan(5, 'Starter'); // mixed case normalised internally
    }

    // -------------------------------------------------------------------------
    // changePlan — tenant not found after update (affected = 0, tenant missing)
    // -------------------------------------------------------------------------

    public function testChangePlanTenantNotFoundThrowsWhenUpdateAffectsZero(): void
    {
        $connection = $this->dbConnection(true);

        // fetchOne calls:
        //   1. planExists   → plan found
        //   2. tenant exists check → false (not found)
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls('starter', false);

        $connection->method('update')->willReturn(0);

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $manager->changePlan(99, 'starter');
    }

    // -------------------------------------------------------------------------
    // changePlan — update affects 0 rows but tenant exists (plan unchanged case)
    // -------------------------------------------------------------------------

    public function testChangePlanWithNoUpdateButTenantExistsDoesNotThrow(): void
    {
        $connection = $this->dbConnection(true);

        // fetchOne: planExists → found, tenant exists → found (id returned)
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls('gold', 42);

        // update returns 0 (same plan, no rows changed) — must NOT throw
        $connection->method('update')->willReturn(0);

        // getTenant (called inside changePlan for cache invalidation)
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 42, 'name' => 'globex', 'connection' => 'default', 'status' => 'active', 'plan' => 'gold']);

        $connection->method('fetchAllAssociative')->willReturn([]);

        $manager = $this->dbManager($connection);
        $manager->changePlan(42, 'gold'); // no exception expected
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // addTenantDomain — duplicate domain throws InvalidArgumentException
    // -------------------------------------------------------------------------

    public function testAddTenantDomainDuplicateDomainThrows(): void
    {
        $connection = $this->dbConnection(true);
        // fetchOne for existing domain check → returns a non-false id
        $connection->method('fetchOne')->willReturn(7);

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/i');
        $manager->addTenantDomain(3, 'tenant.example.test');
    }

    // -------------------------------------------------------------------------
    // getTenantDomains — returns rows
    // -------------------------------------------------------------------------

    public function testGetTenantDomainsReturnsDomainRows(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'domain' => 'primary.example.test', 'is_primary' => 1],
            ['id' => 2, 'domain' => 'alias.example.test', 'is_primary' => 0],
        ]);

        $manager = $this->dbManager($connection);
        $domains = $manager->getTenantDomains(5);

        self::assertCount(2, $domains);
        self::assertSame('primary.example.test', $domains[0]['domain']);
    }

    // -------------------------------------------------------------------------
    // requireControlPlaneReady — throws RuntimeException when DB not ready
    // -------------------------------------------------------------------------

    public function testGetTenantDomainsThrowsWhenControlPlaneNotReady(): void
    {
        $manager = $this->dbManager($this->dbConnection(false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready/');
        $manager->getTenantDomains(1);
    }

    public function testSuspendTenantThrowsWhenControlPlaneNotReady(): void
    {
        $manager = $this->dbManager($this->dbConnection(false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready/');
        $manager->suspendTenant(1);
    }

    public function testRestoreTenantThrowsWhenControlPlaneNotReady(): void
    {
        $manager = $this->dbManager($this->dbConnection(false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready/');
        $manager->restoreTenant(1);
    }

    public function testPurgeTenantThrowsWhenControlPlaneNotReady(): void
    {
        $manager = $this->dbManager($this->dbConnection(false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready/');
        $manager->purgeTenant(1);
    }

    public function testChangePlanThrowsWhenControlPlaneNotReady(): void
    {
        $manager = $this->dbManager($this->dbConnection(false));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready/');
        $manager->changePlan(1, 'starter');
    }

    // -------------------------------------------------------------------------
    // YAML fallback — RuntimeException when no YAML file and DB not ready
    // -------------------------------------------------------------------------

    public function testFallbackToYamlThrowsWhenNoYamlFileAndDbNotReady(): void
    {
        // Use a path that will never be created (no YAML file available)
        $nonExistentPath = sys_get_temp_dir() . '/efact-no-such-file-' . bin2hex(random_bytes(8)) . '.yml';

        $manager = new TenantManager(
            $nonExistentPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Control plane database not ready and YAML fallback unavailable/');
        $manager->getTenants();
    }

    // -------------------------------------------------------------------------
    // YAML fallback — falls back and logs warning when YAML file exists
    // -------------------------------------------------------------------------

    public function testFallbackToYamlLogsWarningAndReturnsTenants(): void
    {
        $configPath = $this->tempPath('tenants-fallback-warn.yml');
        file_put_contents($configPath, "- { id: 10, name: fallback-tenant, connection: default }\n");

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('warning')
            ->with(
                self::stringContains('Control plane not ready'),
                self::arrayHasKey('operation'),
            );

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $logger,
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $tenants = $manager->getTenants();
        self::assertCount(1, $tenants);
        self::assertSame('fallback-tenant', $tenants[0]['name']);
    }

    // -------------------------------------------------------------------------
    // getTenant — DB mode: found and not found
    // -------------------------------------------------------------------------

    public function testGetTenantInDatabaseModeReturnsRow(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 3, 'name' => 'acme', 'connection' => 'default', 'status' => 'active', 'plan' => 'starter']);

        $manager = $this->dbManager($connection);
        $tenant = $manager->getTenant(3);

        self::assertNotNull($tenant);
        self::assertSame('acme', $tenant['name']);
    }

    public function testGetTenantInDatabaseModeReturnsNullWhenNotFound(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')->willReturn(false);

        $manager = $this->dbManager($connection);
        self::assertNull($manager->getTenant(999));
    }

    // -------------------------------------------------------------------------
    // getTenantByName — DB mode: found
    // -------------------------------------------------------------------------

    public function testGetTenantByNameInDatabaseModeReturnsRow(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 4, 'name' => 'globex', 'connection' => 'globex_db', 'status' => 'active', 'plan' => 'gold']);

        $manager = $this->dbManager($connection);
        $tenant = $manager->getTenantByName('globex');

        self::assertNotNull($tenant);
        self::assertSame('globex', $tenant['name']);
    }

    public function testGetTenantByNameInDatabaseModeReturnsNullWhenNotFound(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')->willReturn(false);

        $manager = $this->dbManager($connection);
        self::assertNull($manager->getTenantByName('unknown'));
    }

    // -------------------------------------------------------------------------
    // getTenantByDomain — DB mode: found and not found
    // -------------------------------------------------------------------------

    public function testGetTenantByDomainInDatabaseModeReturnsRow(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 5, 'name' => 'acme', 'connection' => 'acme_db', 'status' => 'active']);

        $manager = $this->dbManager($connection);
        $tenant = $manager->getTenantByDomain('acme.example.test');

        self::assertNotNull($tenant);
        self::assertSame('acme', $tenant['name']);
    }

    public function testGetTenantByDomainInDatabaseModeReturnsNullWhenNotFound(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')->willReturn(false);

        $manager = $this->dbManager($connection);
        self::assertNull($manager->getTenantByDomain('nobody.example.test'));
    }

    // -------------------------------------------------------------------------
    // updateBackupFrequency — success (affected = 1) and tenant-not-found
    // -------------------------------------------------------------------------

    public function testUpdateBackupFrequencySuccessPath(): void
    {
        $connection = $this->dbConnection(true);
        // update returns 1 → success branch; getTenant called for cache
        $connection->method('update')->willReturn(1);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 7, 'name' => 'acme', 'connection' => 'default']);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $manager = $this->dbManager($connection);
        $manager->updateBackupFrequency(7, 24);
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testUpdateBackupFrequencyTenantNotFoundWhenUpdateAffectsZeroAndFetchFalse(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('update')->willReturn(0);
        $connection->method('fetchOne')->willReturn(false); // tenant doesn't exist

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $manager->updateBackupFrequency(999, 12);
    }

    // -------------------------------------------------------------------------
    // updateBackupRetention — success (affected = 1) and tenant-not-found
    // -------------------------------------------------------------------------

    public function testUpdateBackupRetentionSuccessPath(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('update')->willReturn(1);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 8, 'name' => 'globex', 'connection' => 'default']);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $manager = $this->dbManager($connection);
        $manager->updateBackupRetention(8, 30);
        $this->addToAssertionCount(1);
    }

    public function testUpdateBackupRetentionTenantNotFoundWhenUpdateAffectsZeroAndFetchFalse(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('update')->willReturn(0);
        $connection->method('fetchOne')->willReturn(false);

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $manager->updateBackupRetention(999, 7);
    }

    // -------------------------------------------------------------------------
    // addTenant — DB mode: duplicate tenant name throws
    // -------------------------------------------------------------------------

    public function testAddTenantInDatabaseModeDuplicateThrows(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchOne')->willReturn(1); // existing tenant found

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/');
        $manager->addTenant(['name' => 'acme', 'connection' => 'default']);
    }

    // -------------------------------------------------------------------------
    // deleteTenant — DB mode: cache invalidated after deletion
    // -------------------------------------------------------------------------

    public function testDeleteTenantInDatabaseModeInvalidatesCache(): void
    {
        $connection = $this->dbConnection(true);

        // getTenant call → returns the tenant (for cache invalidation after delete)
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 11, 'name' => 'delme', 'connection' => 'default', 'status' => 'active']);

        // The domain cache invalidation calls fetchAllAssociative (getTenantDomains)
        $connection->method('fetchAllAssociative')->willReturn([]);

        $connection->expects(self::once())
            ->method('update')
            ->with(
                'tenants',
                self::callback(static fn (array $d): bool => $d['status'] === 'deleted'),
                ['id' => 11],
            );

        $manager = $this->dbManager($connection);
        $manager->deleteTenant(11);
    }

    // -------------------------------------------------------------------------
    // updateTenant — DB mode: duplicate name throws; tenant not found throws
    // -------------------------------------------------------------------------

    public function testUpdateTenantInDatabaseModeDuplicateNameThrows(): void
    {
        $connection = $this->dbConnection(true);
        // First fetchAssociative (getTenant for old cache) → found
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 12, 'name' => 'old-name', 'connection' => 'default']);
        // fetchOne for duplicate check → found (another tenant has the same name)
        $connection->method('fetchOne')->willReturn(99);

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/');
        $manager->updateTenant(12, ['name' => 'taken-name', 'connection' => 'conn']);
    }

    public function testUpdateTenantInDatabaseModeNotFoundThrows(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')
            ->willReturn(['id' => 13, 'name' => 'old', 'connection' => 'default']);
        // No duplicate found
        $connection->method('fetchOne')->willReturn(false);
        // update returns 0 → tenant not found
        $connection->method('update')->willReturn(0);

        $manager = $this->dbManager($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $manager->updateTenant(13, ['name' => 'new-name', 'connection' => 'conn']);
    }

    // -------------------------------------------------------------------------
    // YAML mode: addTenant duplicate throws
    // -------------------------------------------------------------------------

    public function testYamlAddTenantDuplicateThrows(): void
    {
        $configPath = $this->tempPath('tenants-dup.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/');
        $manager->addTenant(['name' => 'acme', 'connection' => 'conn2']);
    }

    // -------------------------------------------------------------------------
    // YAML mode: updateTenant duplicate name throws
    // -------------------------------------------------------------------------

    public function testYamlUpdateTenantDuplicateNameThrows(): void
    {
        $configPath = $this->tempPath('tenants-upd-dup.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: c1 }\n- { id: 2, name: globex, connection: c2 }\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/');
        $manager->updateTenant(1, ['name' => 'globex', 'connection' => 'c1']);
    }

    // -------------------------------------------------------------------------
    // YAML mode: updateTenant not found throws
    // -------------------------------------------------------------------------

    public function testYamlUpdateTenantNotFoundThrows(): void
    {
        $configPath = $this->tempPath('tenants-upd-nf.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: c1 }\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant not found');
        $manager->updateTenant(999, ['name' => 'nobody', 'connection' => 'c1']);
    }

    // -------------------------------------------------------------------------
    // YAML mode: empty / blank YAML returns empty array
    // -------------------------------------------------------------------------

    public function testYamlEmptyFileReturnsEmptyTenants(): void
    {
        $configPath = $this->tempPath('tenants-blank.yml');
        file_put_contents($configPath, '   ');

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        self::assertSame([], $manager->getTenants());
    }

    // -------------------------------------------------------------------------
    // YAML mode: non-array YAML content returns empty array
    // -------------------------------------------------------------------------

    public function testYamlNonArrayContentReturnsEmptyTenants(): void
    {
        $configPath = $this->tempPath('tenants-scalar.yml');
        file_put_contents($configPath, "just a string\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        self::assertSame([], $manager->getTenants());
    }

    // -------------------------------------------------------------------------
    // DB mode: addTenantInDatabase falls back to YAML when control plane is down
    // -------------------------------------------------------------------------

    public function testAddTenantInDatabaseFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-add-fallback.yml');
        file_put_contents($configPath, "[]\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $manager->addTenant(['name' => 'new-tenant', 'connection' => 'new_conn']);

        $tenants = (new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        ))->getTenants();

        self::assertCount(1, $tenants);
        self::assertSame('new-tenant', $tenants[0]['name']);
    }

    // -------------------------------------------------------------------------
    // DB mode: updateTenant falls back to YAML when control plane is down
    // -------------------------------------------------------------------------

    public function testUpdateTenantInDatabaseFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-upd-fallback.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: c1 }\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $manager->updateTenant(1, ['name' => 'acme-updated', 'connection' => 'c2']);

        $tenants = (new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        ))->getTenants();

        self::assertSame('acme-updated', $tenants[0]['name']);
    }

    // -------------------------------------------------------------------------
    // DB mode: deleteTenant falls back to YAML when control plane is down
    // -------------------------------------------------------------------------

    public function testDeleteTenantInDatabaseFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-del-fallback.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: c1 }\n- { id: 2, name: globex, connection: c2 }\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $manager->deleteTenant(1);

        $tenants = (new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        ))->getTenants();

        self::assertCount(1, $tenants);
        self::assertSame('globex', $tenants[0]['name']);
    }

    // -------------------------------------------------------------------------
    // DB mode: getTenant falls back to YAML when control plane is down
    // -------------------------------------------------------------------------

    public function testGetTenantInDatabaseFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-get-fallback.yml');
        file_put_contents($configPath, "- { id: 3, name: acme, connection: c1 }\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $tenant = $manager->getTenant(3);
        self::assertNotNull($tenant);
        self::assertSame('acme', $tenant['name']);

        self::assertNull($manager->getTenant(999));
    }

    // -------------------------------------------------------------------------
    // DB mode: getTenantByName falls back to YAML when control plane is down
    // -------------------------------------------------------------------------

    public function testGetTenantByNameInDatabaseFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-byname-fallback.yml');
        file_put_contents($configPath, "- { id: 5, name: globex, connection: c1 }\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        $tenant = $manager->getTenantByName('globex');
        self::assertNotNull($tenant);
        self::assertSame('globex', $tenant['name']);
    }

    // -------------------------------------------------------------------------
    // DB mode: getTenantByDomain falls back to YAML (returns null, no domain lookup in YAML)
    // -------------------------------------------------------------------------

    public function testGetTenantByDomainInDatabaseFallsBackToNullWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-domain-fallback.yml');
        file_put_contents($configPath, "- { id: 5, name: globex, connection: c1 }\n");

        $manager = new TenantManager(
            $configPath,
            'db',
            $this->dbRegistry($this->dbConnection(false)),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );

        // YAML fallback path for domain lookup returns null
        self::assertNull($manager->getTenantByDomain('globex.example.test'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function dbManager(Connection $connection): TenantManager
    {
        return new TenantManager(
            $this->tempPath('tenants-lifecycle.yml'),
            'db',
            $this->dbRegistry($connection),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class),
        );
    }

    private function dbRegistry(Connection $connection): ConnectionRegistry
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return $registry;
    }

    /**
     * @return MockObject&Connection
     */
    private function dbConnection(bool $ready): Connection
    {
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn($ready);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);

        return $connection;
    }

    private function tempPath(string $file): string
    {
        $dir = sys_get_temp_dir() . '/efact-lifecycle-' . bin2hex(random_bytes(4));
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $file;
    }

    private function inMemoryCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            /**
             * @param array<string, mixed>|null $metadata
             */
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                if (!array_key_exists($key, $this->store)) {
                    $item = new class () implements ItemInterface {
                        public function getKey(): string
                        {
                            return 'cache-item';
                        }

                        public function get(): mixed
                        {
                            return null;
                        }

                        public function isHit(): bool
                        {
                            return false;
                        }

                        public function set(mixed $value): static
                        {
                            return $this;
                        }

                        public function expiresAt(?\DateTimeInterface $expiration): static
                        {
                            return $this;
                        }

                        public function expiresAfter(int|\DateInterval|null $time): static
                        {
                            return $this;
                        }

                        public function tag(string|iterable $tags): static
                        {
                            return $this;
                        }

                        /**
                         * @return array<string, mixed>
                         */
                        public function getMetadata(): array
                        {
                            return [];
                        }
                    };
                    $this->store[$key] = $callback($item, true);
                }

                return $this->store[$key];
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }
        };
    }
}
