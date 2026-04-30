<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\ControlPlane\Billing\Service\PlanManager;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PlanManagerTest extends TestCase
{
    private ConnectionRegistry&MockObject $registry;
    private TenantResourceCatalog $resourceCatalog;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ConnectionRegistry::class);
        $this->resourceCatalog = new TenantResourceCatalog();
    }

    public function testGetLimitsForPlanFailsClosedWhenPlansUnavailable(): void
    {
        $manager = new PlanManager($this->registry, $this->cacheReturning([]), new NullLogger(), $this->resourceCatalog);

        $limits = $manager->getLimitsForPlan('free');

        self::assertSame(0, $limits['users']);
        self::assertSame(0, $limits['customers']);
        self::assertSame(0, $limits['products']);
        self::assertSame(0, $limits['invoices']);
        self::assertSame(0, $limits['branches']);
        self::assertSame(0, $limits['storage_mb']);
    }

    public function testGetLimitsForPlanReturnsExactOrDefaultPlanLimits(): void
    {
        $plans = [
            ['id' => 1, 'name' => 'free', 'limits' => ['users' => 1], 'is_default' => true],
            ['id' => 2, 'name' => 'pro', 'limits' => ['users' => 20], 'is_default' => false],
        ];

        $manager = new PlanManager($this->registry, $this->cacheReturning($plans), new NullLogger(), $this->resourceCatalog);

        self::assertSame(['users' => 20], $manager->getLimitsForPlan('pro'));
        self::assertSame(['users' => 1], $manager->getLimitsForPlan('missing'));
        self::assertTrue($manager->planExists('free'));
        self::assertFalse($manager->planExists('enterprise'));
        self::assertSame(['free' => ['users' => 1], 'pro' => ['users' => 20]], $manager->getPlansAsLimitsMap());
    }

    public function testGetPlanAndGetPlanByNameHydrateDatabaseRows(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturnMap([
            ['SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [7], [], [
                'id' => 7,
                'name' => 'starter',
                'limits_json' => '{"users":3}',
                'is_default' => 0,
            ]],
            ['SELECT id, name, limits_json, is_default FROM plans WHERE name = ?', ['starter'], [], [
                'id' => 7,
                'name' => 'starter',
                'limits_json' => '{"users":3}',
                'is_default' => 0,
            ]],
            ['SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [999], [], false],
        ]);

        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $manager = new PlanManager($this->registry, $this->cacheReturning([]), new NullLogger(), $this->resourceCatalog);

        self::assertSame(['id' => 7, 'name' => 'starter', 'limits' => ['users' => 3], 'is_default' => false], $manager->getPlan(7));
        self::assertSame(['id' => 7, 'name' => 'starter', 'limits' => ['users' => 3], 'is_default' => false], $manager->getPlanByName('starter'));
        self::assertNull($manager->getPlan(999));
    }

    public function testCreatePlanRejectsInvalidInputs(): void
    {
        $connection = $this->createStub(Connection::class);
        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $manager = new PlanManager($this->registry, $this->cacheReturning([]), new NullLogger(), $this->resourceCatalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan name is required');
        $manager->createPlan('   ', ['users' => 10]);
    }

    public function testCreatePlanRejectsInvalidNameAndDuplicatesAndLimitRange(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $manager = new PlanManager($this->registry, $this->cacheReturning([]), new NullLogger(), $this->resourceCatalog);

        try {
            $manager->createPlan('Pro Plan', ['users' => 10]);
            self::fail('Expected invalid name exception');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('only lowercase letters', $e->getMessage());
        }

        try {
            $manager->createPlan('pro', ['users' => -2]);
            self::fail('Expected invalid limit exception');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('must be -1 or greater', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');
        $manager->createPlan('pro', ['users' => 10]);
    }

    public function testCreatePlanPersistsNormalizedLimitsAndInvalidatesCache(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::once())->method('executeStatement')->with('UPDATE plans SET is_default = 0');
        $connection->expects(self::once())->method('insert')->with(
            'plans',
            self::callback(function (array $data): bool {
                $limits = json_decode((string) $data['limits_json'], true);

                return $data['name'] === 'enterprise'
                    && $data['is_default'] === 1
                    && $limits['users'] === 50
                    && $limits['customers'] === -1
                    && $limits['storage_mb'] === -1;
            })
        );

        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $cache = $this->cacheReturning([]);
        $cache->expects(self::once())->method('delete')->with('plans_all');

        $manager = new PlanManager($this->registry, $cache, new NullLogger(), $this->resourceCatalog);
        $manager->createPlan('enterprise', ['users' => 50], true);
    }

    public function testUpdatePlanAndDeletePlanValidateAndApplyRules(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturnMap([
            ['SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [5], [], [
                'id' => 5,
                'name' => 'pro',
                'limits_json' => '{"users":10}',
                'is_default' => 0,
            ]],
            ['SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [6], [], [
                'id' => 6,
                'name' => 'free',
                'limits_json' => '{"users":1}',
                'is_default' => 1,
            ]],
            ['SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [99], [], false],
        ]);
        $connection->method('fetchOne')->willReturnMap([
            ['SELECT id FROM plans WHERE name = ? AND id <> ?', ['pro-plus', 5], [], false],
            ['SELECT COUNT(*) FROM tenants WHERE plan = ?', ['free'], [], 2],
            ['SELECT COUNT(*) FROM tenants WHERE plan = ?', ['pro'], [], 0],
        ]);

        $connection->expects(self::once())->method('executeStatement')->with('UPDATE plans SET is_default = 0');
        $connection->expects(self::exactly(2))->method('update');
        $connection->expects(self::once())->method('delete')->with('plans', ['id' => 5]);

        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $cache = $this->cacheReturning([]);
        $cache->expects(self::exactly(2))->method('delete')->with('plans_all');

        $manager = new PlanManager($this->registry, $cache, new NullLogger(), $this->resourceCatalog);

        $manager->updatePlan(5, 'pro-plus', ['users' => 25], true);

        try {
            $manager->deletePlan(99);
            self::fail('Expected not found');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Plan not found', $e->getMessage());
        }

        try {
            $manager->deletePlan(6);
            self::fail('Expected cannot delete default');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('default plan', $e->getMessage());
        }

        $manager->deletePlan(5);
    }

    /**
     * @param array<int, array{id:int, name:string, limits:array<string,int>, is_default:bool}> $plans
     */
    private function cacheReturning(array $plans): CacheInterface&MockObject
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($plans): array {
            unset($key, $callback);

            return $plans;
        });

        return $cache;
    }
}
