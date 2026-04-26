<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Service;

/**
 * Provides a per-tenant resource usage summary for the entitlements API.
 *
 * Returns a map of resource key → { current: int, limit: int }.
 * The special key "storage_bytes" carries raw byte values for storage.
 */
interface TenantUsageSummaryInterface
{
    /**
     * @return array<string, array{current: int, limit: int}>
     */
    public function getUsageSummary(string $tenantName): array;
}
