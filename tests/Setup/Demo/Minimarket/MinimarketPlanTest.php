<?php

declare(strict_types=1);

namespace App\Tests\Setup\Demo\Minimarket;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Domain\Model\TenantSnapshot;
use App\ControlPlane\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Setup\Demo\Minimarket\MinimarketPlan;
use PHPUnit\Framework\TestCase;

final class MinimarketPlanTest extends TestCase
{
    private function plan(?TenantSnapshot $tenant, int $expectedChanges): MinimarketPlan
    {
        $tenants = $this->createStub(TenantRepositoryInterface::class);
        $tenants->method('findByName')->willReturn($tenant);

        $service = $this->createMock(TenantService::class);
        $service->expects(self::exactly($expectedChanges))->method('changePlan')->with(7, MinimarketPlan::PLAN);

        return new MinimarketPlan($tenants, $service);
    }

    public function testMovesAFreeTenantToTheBasicPlan(): void
    {
        self::assertTrue($this->plan(
            new TenantSnapshot(id: 7, name: 'minimarket', connection: 'minimarket', plan: 'free'),
            1,
        )->ensure('minimarket'));
    }

    public function testLeavesATenantAlreadyOnTheBasicPlanAlone(): void
    {
        self::assertFalse($this->plan(
            new TenantSnapshot(id: 7, name: 'minimarket', connection: 'minimarket', plan: 'basic'),
            0,
        )->ensure('minimarket'));
    }

    public function testDoesNothingForATenantThatIsNotRegistered(): void
    {
        self::assertFalse($this->plan(null, 0)->ensure('desconocido'));
    }
}
