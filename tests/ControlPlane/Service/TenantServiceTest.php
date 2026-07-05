<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Service;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Infrastructure\Persistence\YamlTenantStore;
use App\Tests\ControlPlane\Support\TenantServiceFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantServiceTest extends TestCase
{
    public function testConstructorRequiresConfigPathWhenYamlIsEnabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenants config path is required');

        new YamlTenantStore('');
    }

    public function testYamlCrudFlow(): void
    {
        $configPath = $this->tempPath('tenants.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = TenantServiceFactory::yaml($configPath);

        self::assertCount(1, $manager->getTenants());

        $manager->addTenant([
            'name' => 'globex',
            'connection' => 'globex_conn',
        ]);

        $globex = $manager->getTenantByName('globex');
        self::assertNotNull($globex);
        self::assertSame('globex_conn', $globex['connection']);
        self::assertSame(2, $globex['id']);

        $manager->updateTenant(2, [
            'name' => 'globex-new',
            'connection' => 'globex_v2',
        ]);
        $updated = $manager->getTenant(2);
        self::assertNotNull($updated);
        self::assertSame('globex-new', $updated['name']);
        self::assertSame('globex_v2', $updated['connection']);

        $manager->deleteTenant(2);
        self::assertNull($manager->getTenant(2));
        self::assertNull($manager->getTenantByDomain('tenant.example.test'));
    }

    public function testYamlCrudValidations(): void
    {
        $configPath = $this->tempPath('tenants-validate.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = TenantServiceFactory::yaml($configPath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name and connection are required');
        $manager->addTenant(['name' => 'acme']);
    }

    public function testGetTenantByNameReturnsNullWhenBlank(): void
    {
        $configPath = $this->tempPath('tenants-empty.yml');
        file_put_contents($configPath, "[]\n");

        $manager = TenantServiceFactory::yaml($configPath);

        self::assertNull($manager->getTenantByName('   '));
        self::assertSame([], $manager->getDeletedTenants());
    }

    public function testGetDeletedTenantsReturnsRowsInDatabaseMode(): void
    {
        $connection = $this->dbConnection(true);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 7, 'name' => 'deleted-one'],
            ]);

        $manager = $this->dbManager($connection);
        $rows = $manager->getDeletedTenants();

        self::assertCount(1, $rows);
        self::assertSame('deleted-one', $rows[0]['name']);
    }

    public function testAddTenantDomainRejectsBlankDomain(): void
    {
        $manager = $this->dbManager($this->dbConnection(true));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain is required');
        $manager->addTenantDomain(1, '   ');
    }

    public function testAddTenantDomainCreatesPrimaryDomain(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchOne')->willReturn(false);
        $connection
            ->expects(self::once())
            ->method('update')
            ->with('tenant_domains', ['is_primary' => 0], ['tenant_id' => 3]);
        $connection
            ->expects(self::once())
            ->method('insert')
            ->with('tenant_domains', [
                'tenant_id' => 3,
                'domain' => 'tenant.example.test',
                'is_primary' => 1,
            ]);

        $manager = $this->dbManager($connection);
        $manager->addTenantDomain(3, 'Tenant.Example.Test', true);
    }

    public function testDeleteAndSetPrimaryDomainCallsExpectedQueries(): void
    {
        $connection = $this->dbConnection(true);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'domain' => 'tenant.example.test',
            ]);
        $connection->expects(self::once())->method('delete')->with('tenant_domains', ['id' => 15]);
        $connection->expects(self::exactly(2))->method('update')->willReturnOnConsecutiveCalls(1, 1);

        $manager = $this->dbManager($connection);
        $manager->deleteTenantDomain(15);
        $manager->setPrimaryTenantDomain(9, 15);
    }

    public function testGetPrimaryDomainReturnsNullAndString(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, 'acme.example.test');

        $manager = $this->dbManager($connection);
        self::assertNull($manager->getPrimaryDomain(2));
        self::assertSame('acme.example.test', $manager->getPrimaryDomain(2));
    }

    public function testPurgeTenantRequiresDeletedStatus(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            false,
            ['id' => 1, 'status' => 'active'],
            ['id' => 1, 'status' => 'deleted'],
        );
        $connection->expects(self::once())->method('delete')->with('tenants', ['id' => 1]);

        $manager = $this->dbManager($connection);

        try {
            $manager->purgeTenant(1);
            self::fail('Expected not found exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Tenant not found', $exception->getMessage());
        }

        try {
            $manager->purgeTenant(1);
            self::fail('Expected active tenant exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Only deleted tenants', $exception->getMessage());
        }

        $manager->purgeTenant(1);
    }

    public function testSuspendAndUnsuspendValidateAffectedRows(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('update')->willReturnOnConsecutiveCalls(0, 1, 0, 1);
        $connection->method('fetchAssociative')->willReturn(['id' => 10, 'name' => 'acme', 'connection' => 'default']);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $manager = $this->dbManager($connection);

        try {
            $manager->suspendTenant(10);
            self::fail('Expected active tenant not found');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Active tenant not found', $exception->getMessage());
        }

        $manager->suspendTenant(10);

        try {
            $manager->unsuspendTenant(10);
            self::fail('Expected suspended tenant not found');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Suspended tenant not found', $exception->getMessage());
        }

        $manager->unsuspendTenant(10);
    }

    public function testRestoreAndBackupUpdatesValidateConstraints(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchAssociative')->willReturn(['id' => 2, 'name' => 'acme', 'connection' => 'default']);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('update')->willReturnOnConsecutiveCalls(
            0, // restore fail
            1, // restore ok
            0, // backup frequency affected
            1, // backup retention affected
        );
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(2); // backup frequency exists

        $manager = $this->dbManager($connection);

        try {
            $manager->restoreTenant(2);
            self::fail('Expected deleted tenant not found');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Deleted tenant not found', $exception->getMessage());
        }

        $manager->restoreTenant(2);
        $manager->updateBackupFrequency(2, 12);
        $manager->updateBackupRetention(2, 30);
    }

    public function testBackupAndPlanValidationErrors(): void
    {
        $connection = $this->dbConnection(true);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(
            false, // plan exists?
            false, // tenant exists?
        );
        $connection->method('update')->willReturn(0);

        $manager = $this->dbManager($connection);

        try {
            $manager->updateBackupFrequency(2, -1);
            self::fail('Expected backup frequency validation');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Backup frequency', $exception->getMessage());
        }

        try {
            $manager->updateBackupRetention(2, -3);
            self::fail('Expected backup retention validation');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Backup retention days', $exception->getMessage());
        }

        try {
            $manager->changePlan(2, '');
            self::fail('Expected plan required validation');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Plan is required', $exception->getMessage());
        }

        try {
            $manager->changePlan(2, 'premium');
            self::fail('Expected invalid plan');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Invalid plan', $exception->getMessage());
        }
    }

    public function testDatabaseModeFallsBackToYamlWhenControlPlaneNotReady(): void
    {
        $configPath = $this->tempPath('tenants-fallback.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = TenantServiceFactory::database($this->dbConnection(false), $configPath);

        $tenants = $manager->getTenants();
        self::assertCount(1, $tenants);
        self::assertSame('acme', $tenants[0]['name']);
    }

    private function dbManager(Connection $connection): TenantService
    {
        return TenantServiceFactory::database($connection, $this->tempPath('tenants-db.yml'));
    }

    /**
     * @return MockObject&Connection
     */
    private function dbConnection(bool $ready): Connection
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn($ready);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);

        return $connection;
    }

    private function tempPath(string $file): string
    {
        $dir = sys_get_temp_dir() . '/efact-tests-' . bin2hex(random_bytes(4));
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $file;
    }
}
