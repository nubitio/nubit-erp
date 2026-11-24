<?php

declare(strict_types=1);

namespace App\Settings\Capability;

/**
 * Reads effective capabilities and vertical from the tenant runtime config.
 */
final class TenantCapabilityAvailability
{
    /**
     * @param array<string, mixed> $runtimeConfig
     */
    public function restaurantEnabled(array $runtimeConfig): bool
    {
        $profile = is_array($runtimeConfig['profile'] ?? null) ? $runtimeConfig['profile'] : [];

        return ($profile['vertical'] ?? null) === 'restaurant'
        && $this->isEnabled($runtimeConfig, 'restaurant.table_management');
    }

    /**
     * @param array<string, mixed> $runtimeConfig
     */
    public function isEnabled(array $runtimeConfig, string $key): bool
    {
        $capabilities = is_array($runtimeConfig['capabilities'] ?? null) ? $runtimeConfig['capabilities'] : [];
        $capability = $capabilities[$key] ?? null;

        return is_array($capability) && ($capability['enabled'] ?? false) === true;
    }
}
