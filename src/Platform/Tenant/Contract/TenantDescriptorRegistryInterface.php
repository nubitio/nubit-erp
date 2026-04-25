<?php

declare(strict_types=1);

namespace App\Platform\Tenant\Contract;

use App\Platform\Tenant\Model\TenantDescriptor;

/**
 * Typed tenant registry port for new Platform consumers.
 *
 * Adapters can wrap the legacy TenantRegistryInterface and map tenant arrays to
 * TenantDescriptor without forcing an immediate implementation migration.
 */
interface TenantDescriptorRegistryInterface
{
    /**
     * @return list<TenantDescriptor>
     */
    public function tenants(): array;

    public function findByName(string $name): ?TenantDescriptor;

    public function findByDomain(string $domain): ?TenantDescriptor;
}
