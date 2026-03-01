<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\Core\Context\TenantContext;
use App\Core\Doctrine\DBAL\TenantConnection;
use App\Core\Exception\QuotaExceededException;
use App\Core\Exception\ServiceException;
use App\Core\Service\PlanManager;
use App\Core\Service\QuotaCheckResult;
use App\Core\Service\TenantManager;
use App\Core\Service\TenantQuotaService;
use App\Core\Service\TenantResourceCatalog;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class TenantQuotaServiceTest extends TestCase
{
    public function testCheckReturnsOpenWhenNoTenantContextAndClosedForUnknownResource(): void
    {
        $service = $this->newService(
            tenantContext: new TenantContext(),
            tenantManager: $this->createMock(TenantManager::class),
            planManager: $this->planManagerWithPlans([]),
            registry: $this->createMock(ConnectionRegistry::class)
        );

        $open = $service->check('users');
        $unknown = $service->check('unknown-resource');

        self::assertEquals(new QuotaCheckResult(true, 'users', 0, -1), $open);
        self::assertEquals(new QuotaCheckResult(false, 'unknown-resource', 0, 0), $unknown);
    }

    public function testCheckFailsClosedWhenTenantContextExistsButTenantIsMissing(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(10, 'acme', 'acme.example.com', 'req-1');

        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenantByName')->with('acme')->willReturn(null);

        $service = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $this->planManagerWithPlans([]),
            registry: $this->createMock(ConnectionRegistry::class)
        );

        $result = $service->check('users');

        self::assertFalse($result->allowed);
        self::assertSame(0, $result->current);
        self::assertSame(0, $result->limit);
    }

    public function testCheckAllowsUnlimitedPlansAndFailsWhenTableIsMissing(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);

        $tenant = ['id' => 1, 'name' => 'acme', 'connection' => 'acme_db', 'plan' => 'free'];
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenantByName')->with('acme')->willReturn($tenant);

        $tenantConnectionUnlimited = $this->createMock(TenantConnection::class);
        $tenantConnectionUnlimited->method('switchTenantParams');
        $tenantConnectionUnlimited->method('quoteSingleIdentifier')->willReturn('`usuario`');
        $schemaUnlimited = $this->createMock(AbstractSchemaManager::class);
        $schemaUnlimited->method('listTableNames')->willReturn(['usuario']);
        $tenantConnectionUnlimited->method('createSchemaManager')->willReturn($schemaUnlimited);

        $registryUnlimited = $this->createMock(ConnectionRegistry::class);
        $registryUnlimited->method('getConnection')->with('tenant')->willReturn($tenantConnectionUnlimited);

        $serviceUnlimited = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $this->planManagerWithPlans([
                ['id' => 1, 'name' => 'free', 'limits' => ['users' => -1], 'is_default' => true],
            ]),
            registry: $registryUnlimited
        );

        $unlimited = $serviceUnlimited->check('users');
        self::assertTrue($unlimited->allowed);
        self::assertSame(-1, $unlimited->limit);

        $tenantConnectionMissing = $this->createMock(TenantConnection::class);
        $tenantConnectionMissing->method('switchTenantParams');
        $schemaMissing = $this->createMock(AbstractSchemaManager::class);
        $schemaMissing->method('listTableNames')->willReturn([]);
        $tenantConnectionMissing->method('createSchemaManager')->willReturn($schemaMissing);
        $registryMissing = $this->createMock(ConnectionRegistry::class);
        $registryMissing->method('getConnection')->with('tenant')->willReturn($tenantConnectionMissing);

        $serviceMissingTable = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $this->planManagerWithPlans([
                ['id' => 1, 'name' => 'free', 'limits' => ['users' => 10], 'is_default' => true],
            ]),
            registry: $registryMissing
        );

        $missing = $serviceMissingTable->check('users');
        self::assertFalse($missing->allowed);
        self::assertSame(0, $missing->limit);
    }

    public function testEnforceThrowsQuotaExceededAndLockFailureServiceException(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);

        $tenant = ['id' => 1, 'name' => 'acme', 'connection' => 'acme_db', 'plan' => 'pro'];
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenantByName')->with('acme')->willReturn($tenant);

        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->method('switchTenantParams');
        $tenantConnection->method('quoteSingleIdentifier')->willReturn('`usuario`');

        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['usuario']);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);
        $tenantConnection->method('fetchOne')->willReturnOnConsecutiveCalls(
            1,
            12,
            0
        );

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('tenant')->willReturn($tenantConnection);

        $planManager = $this->planManagerWithPlans([
            ['id' => 1, 'name' => 'pro', 'limits' => ['users' => 10], 'is_default' => true],
        ]);

        $service = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $planManager,
            registry: $registry
        );

        try {
            $service->enforce('users');
            self::fail('Expected QuotaExceededException');
        } catch (QuotaExceededException $e) {
            self::assertSame('users', $e->resource);
            self::assertSame(12, $e->current);
            self::assertSame(10, $e->limit);
        }

        $service2 = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $planManager,
            registry: $registry
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionCode(429);
        $service2->check('users');
    }

    public function testGetUsageSummaryAndReleaseLocks(): void
    {
        $tenant = ['id' => 1, 'name' => 'acme', 'connection' => 'acme_db', 'plan' => 'pro'];
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenantByName')->willReturnMap([
            ['acme', $tenant],
            ['missing', null],
        ]);

        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->method('switchTenantParams');
        $tenantConnection->method('quoteSingleIdentifier')->willReturnCallback(
            static fn (string $table): string => sprintf('`%s`', $table)
        );
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['usuario', 'cliente', 'producto', 'tax_document', 'local']);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $tenantConnection->method('fetchOne')->willReturnCallback(
            static function (string $sql): int {
                return match (true) {
                    str_contains($sql, 'GET_LOCK') => 1,
                    str_contains($sql, '`usuario`') => 3,
                    str_contains($sql, '`cliente`') => 4,
                    str_contains($sql, '`producto`') => 5,
                    str_contains($sql, '`tax_document`') => 6,
                    str_contains($sql, '`local`') => 2,
                    str_contains($sql, 'information_schema.tables') => 1048576,
                    default => 0,
                };
            }
        );

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('tenant')->willReturn($tenantConnection);

        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);

        $service = $this->newService(
            tenantContext: $tenantContext,
            tenantManager: $tenantManager,
            planManager: $this->planManagerWithPlans([
                ['id' => 1, 'name' => 'pro', 'limits' => [
                    'users' => 10,
                    'customers' => 20,
                    'products' => 30,
                    'invoices' => 40,
                    'branches' => 2,
                    'storage_mb' => 2,
                ], 'is_default' => true],
            ]),
            registry: $registry
        );

        $service->check('users');
        $summary = $service->getUsageSummary('acme');

        self::assertSame(['current' => 3, 'limit' => 10], $summary['users']);
        self::assertSame(['current' => 1048576, 'limit' => 2097152], $summary['storage_bytes']);

        $service->releaseLocks();
        $service->releaseLocks();

        $this->expectException(\InvalidArgumentException::class);
        $service->getUsageSummary('missing');
    }

    private function newService(
        TenantContext $tenantContext,
        TenantManager $tenantManager,
        PlanManager $planManager,
        ConnectionRegistry $registry,
    ): TenantQuotaService {
        return new TenantQuotaService(
            $tenantContext,
            $tenantManager,
            $planManager,
            new TenantResourceCatalog(),
            $registry
        );
    }

    /**
     * @param array<int, array{id:int, name:string, limits:array<string,int>, is_default:bool}> $plans
     */
    private function planManagerWithPlans(array $plans): PlanManager
    {
        $registry = $this->createMock(ConnectionRegistry::class);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($plans): array {
            unset($key, $callback);

            return $plans;
        });

        return new PlanManager($registry, $cache, new NullLogger(), new TenantResourceCatalog());
    }
}
