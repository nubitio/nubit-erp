<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Domain\Repository\TenantRepositoryInterface;

/**
 * Puts the demo tenant on the plan a paying small business would have.
 *
 * Every plan shows the full day-to-day navigation, so this does not decide the menu. It decides what the
 * plan entitles: `basic` includes Excel export and advanced reports, which a prospect should see, and it
 * leaves out what a single shop does not buy (several warehouses, lot tracking, API access).
 */
final readonly class MinimarketPlan
{
    public const string PLAN = 'basic';

    public function __construct(
        private TenantRepositoryInterface $tenants,
        private TenantService $tenantService,
    ) {}

    /** @return bool true when the plan had to change */
    public function ensure(string $tenantName): bool
    {
        $tenant = $this->tenants->findByName($tenantName);
        if ($tenant === null || $tenant->plan === self::PLAN) {
            return false;
        }

        $this->tenantService->changePlan($tenant->id, self::PLAN);

        return true;
    }
}
