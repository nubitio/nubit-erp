<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Application;

use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;

/**
 * Platform port adapter — read-only tenant registry lookups.
 */
final readonly class TenantRegistry implements TenantRegistryInterface
{
    public function __construct(
        private TenantService $tenantService,
    ) {}

    public function getTenants(): array
    {
        return $this->tenantService->getTenants();
    }

    public function getTenantByName(string $name): ?array
    {
        return $this->tenantService->getTenantByName($name);
    }

    public function getTenantByDomain(string $domain): ?array
    {
        return $this->tenantService->getTenantByDomain($domain);
    }
}
