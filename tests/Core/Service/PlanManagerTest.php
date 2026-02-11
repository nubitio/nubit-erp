<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\Core\Service\PlanManager;
use App\Core\Service\TenantResourceCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;

final class PlanManagerTest extends TestCase
{
    private ConnectionRegistry&MockObject $registry;
    private CacheInterface&MockObject $cache;
    private TenantResourceCatalog $resourceCatalog;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ConnectionRegistry::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resourceCatalog = new TenantResourceCatalog();
    }

    public function testGetLimitsForPlanFailsClosedWhenPlansUnavailable(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback): array {
                unset($key, $callback);

                return [];
            });

        $manager = new PlanManager($this->registry, $this->cache, new NullLogger(), $this->resourceCatalog);
        $limits = $manager->getLimitsForPlan('free');

        $this->assertSame(0, $limits['users']);
        $this->assertSame(0, $limits['customers']);
        $this->assertSame(0, $limits['products']);
        $this->assertSame(0, $limits['invoices']);
        $this->assertSame(0, $limits['branches']);
        $this->assertSame(0, $limits['storage_mb']);
    }

    public function testCreatePlanRejectsInvalidLimitKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid limit key "unknown_key"');

        $manager = new PlanManager($this->registry, $this->cache, new NullLogger(), $this->resourceCatalog);
        $manager->createPlan('enterprise', ['unknown_key' => 10], false);
    }

    public function testCreatePlanRejectsLimitLowerThanMinusOne(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be -1 or greater');

        $manager = new PlanManager($this->registry, $this->cache, new NullLogger(), $this->resourceCatalog);
        $manager->createPlan('enterprise', ['users' => -2], false);
    }
}
