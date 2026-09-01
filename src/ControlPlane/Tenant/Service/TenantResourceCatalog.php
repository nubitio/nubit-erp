<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

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
     * Quota resources whose plan limit is a per-calendar-month allowance
     * (reset on the 1st, America/Lima) rather than a lifetime cap, mapped to
     * the table column holding each record's fiscal date. Everything else in
     * {@see quotaResources()} counts all-time rows.
     *
     * @var array<string, string>
     */
    private const array MONTHLY_QUOTA_DATE_COLUMN = [
        'invoices' => 'issue_date',
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

    /**
     * The date column a monthly quota count must be scoped by, or null when
     * the resource is a lifetime cap.
     */
    public function monthlyQuotaDateColumn(string $resource): ?string
    {
        return self::MONTHLY_QUOTA_DATE_COLUMN[$resource] ?? null;
    }
}
