<?php

declare(strict_types=1);

namespace App\Settings\Capability;

/**
 * Server-side policy for menu destinations. Role assignments decide which
 * users may see an entry; tenant runtime state decides whether the feature
 * exists for this tenant at all.
 */
final class TenantMenuAvailabilityPolicy
{
    public function __construct(
        private readonly TenantCapabilityAvailability $capabilityAvailability = new TenantCapabilityAvailability(),
        private readonly MenuRouteCapabilityMap $routeCapabilities = new MenuRouteCapabilityMap(),
    ) {}

    /**
     * @param array<string, mixed> $runtimeConfig
     */
    public function allows(string $route, array $runtimeConfig): bool
    {
        $path = strtolower(trim($route, characters: '/'));
        if ($path === '') {
            return true;
        }

        $ui = is_array($runtimeConfig['ui'] ?? null) ? $runtimeConfig['ui'] : [];
        $inventoryUi = is_array($ui['inventory'] ?? null) ? $ui['inventory'] : [];

        if (
            in_array($path, ['warehouse', 'inventory/warehouses'], strict: true)
            && ($inventoryUi['showWarehouseManagement'] ?? false) !== true
        ) {
            return false;
        }

        if ($path === 'transfer' && ($inventoryUi['showTransfers'] ?? false) !== true) {
            return false;
        }

        if (str_starts_with($path, 'restaurant/')) {
            return $this->capabilityAvailability->restaurantEnabled($runtimeConfig);
        }

        $requiredCapability = $this->routeCapabilities->capabilityFor($path);

        return $requiredCapability === null
        || $this->capabilityAvailability->isEnabled($runtimeConfig, $requiredCapability);
    }

    /**
     * @param array<string, mixed> $runtimeConfig
     */
    public function restaurantEnabled(array $runtimeConfig): bool
    {
        return $this->capabilityAvailability->restaurantEnabled($runtimeConfig);
    }
}
