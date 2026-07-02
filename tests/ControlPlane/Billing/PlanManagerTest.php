<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing;

use App\ControlPlane\Billing\Service\PlanManager;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PlanManagerTest extends TestCase
{
    /** Build a CacheInterface stub that immediately invokes the callback (no caching). */
    private function passThruCache(): CacheInterface
    {
        $item = $this->createStub(ItemInterface::class);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static fn(string $key, callable $cb): mixed => $cb($item));

        return $cache;
    }

    /** @param array<int, array<string, mixed>> $plans */
    private function makePlanManager(array $plans = []): PlanManager
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn($plans);

        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($conn);

        return new PlanManager($registry, $this->passThruCache(), new NullLogger(), new TenantResourceCatalog());
    }

    /** @return array<int, array<string, mixed>> */
    private static function planRows(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'starter',
                'limits_json' => '{"users":5,"customers":500,"products":200,"invoices":1000,"branches":1,"storage_mb":1024}',
                'is_default' => 1,
            ],
            [
                'id' => 2,
                'name' => 'pro',
                'limits_json' => '{"users":20,"customers":-1,"products":-1,"invoices":-1,"branches":3,"storage_mb":-1}',
                'is_default' => 0,
            ],
        ];
    }

    // ── getPlans ──────────────────────────────────────────────────────────────

    public function testGetPlansReturnsMappedArray(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $plans = $pm->getPlans();

        self::assertCount(2, $plans);
        self::assertSame('starter', $plans[0]['name']);
        self::assertSame('pro', $plans[1]['name']);
        self::assertTrue($plans[0]['is_default']);
        self::assertFalse($plans[1]['is_default']);
    }

    public function testGetPlansHydrateLimitsFromJson(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $plans = $pm->getPlans();

        self::assertSame(5, $plans[0]['limits']['users']);
        self::assertSame(1000, $plans[0]['limits']['invoices']);
        self::assertSame(-1, $plans[1]['limits']['invoices']);
    }

    // ── getLimitsForPlan ──────────────────────────────────────────────────────

    public function testGetLimitsForPlanReturnsMatchingPlanLimits(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $limits = $pm->getLimitsForPlan('starter');

        self::assertSame(5, $limits['users']);
        self::assertSame(200, $limits['products']);
    }

    public function testGetLimitsForUnknownPlanReturnsDefaultPlanLimits(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $limits = $pm->getLimitsForPlan('nonexistent');

        self::assertSame(5, $limits['users']); // falls back to default (starter)
    }

    public function testGetLimitsForPlanReturnsClosedLimitsWhenNoPlans(): void
    {
        $pm = $this->makePlanManager([]);
        $limits = $pm->getLimitsForPlan('starter');

        foreach ($limits as $v) {
            self::assertSame(0, $v);
        }
    }

    // ── getDefaultPlan ────────────────────────────────────────────────────────

    public function testGetDefaultPlanReturnsMarkedDefault(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $default = $pm->getDefaultPlan();

        self::assertNotNull($default);
        self::assertSame('starter', $default['name']);
    }

    public function testGetDefaultPlanReturnFirstWhenNoneMarked(): void
    {
        $rows = [[
            'id' => 1,
            'name' => 'basic',
            'limits_json' => '{"users":3,"customers":100,"products":50,"invoices":200,"branches":1,"storage_mb":512}',
            'is_default' => 0,
        ]];

        $pm = $this->makePlanManager($rows);
        $default = $pm->getDefaultPlan();

        self::assertNotNull($default);
        self::assertSame('basic', $default['name']);
    }

    public function testGetDefaultPlanReturnsNullWhenNoPlans(): void
    {
        self::assertNull($this->makePlanManager([])->getDefaultPlan());
    }

    // ── getPlansAsLimitsMap ───────────────────────────────────────────────────

    public function testGetPlansAsLimitsMapIndexedByName(): void
    {
        $pm = $this->makePlanManager(self::planRows());
        $map = $pm->getPlansAsLimitsMap();

        self::assertArrayHasKey('starter', $map);
        self::assertArrayHasKey('pro', $map);
        self::assertSame(5, $map['starter']['users']);
        self::assertSame(20, $map['pro']['users']);
        self::assertSame(-1, $map['pro']['invoices']);
    }

    // ── createPlan validation ─────────────────────────────────────────────────

    public function testCreatePlanThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan name is required');
        $this->makePlanManager()->createPlan('   ', []);
    }

    public function testCreatePlanThrowsOnInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lowercase letters');
        $this->makePlanManager()->createPlan('Plan Inválido!', []);
    }
}
