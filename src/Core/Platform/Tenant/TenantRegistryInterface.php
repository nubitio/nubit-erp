<?php

declare(strict_types=1);

namespace App\Core\Platform\Tenant;

interface TenantRegistryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByName(string $name): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByDomain(string $domain): ?array;
}
