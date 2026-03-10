<?php

declare(strict_types=1);

namespace App\Core\Service;

final class TenantResourceCatalog
{
    /**
     * @var array<string, list<string>>
     */
    private const array RESOURCE_TABLES = [
        'users' => ['usuario'],
        'customers' => ['cliente'],
        'products' => ['producto'],
        'invoices' => ['tax_document'],
        'branches' => ['local'],
        'cash_shifts' => ['cash_sessions'],
    ];

    /**
     * @return list<string>
     */
    public function quotaResources(): array
    {
        return ['users', 'customers', 'products', 'invoices', 'branches'];
    }

    /**
     * @return list<string>
     */
    public function metricResources(): array
    {
        return array_keys(self::RESOURCE_TABLES);
    }

    /**
     * @return list<string>
     */
    public function tablesForResource(string $resource): array
    {
        return self::RESOURCE_TABLES[$resource] ?? [];
    }

    /**
     * @return list<string>
     */
    public function allowedPlanLimitKeys(): array
    {
        return ['users', 'customers', 'products', 'invoices', 'branches', 'storage_mb'];
    }
}
