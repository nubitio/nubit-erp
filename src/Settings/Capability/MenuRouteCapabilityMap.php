<?php

declare(strict_types=1);

namespace App\Settings\Capability;

/**
 * Maps tenant menu destinations to the operational capability that enables
 * them. Commercial plan checks are already folded into effective capabilities.
 */
final class MenuRouteCapabilityMap
{
    /** @var array<string, string> */
    private const ROUTE_CAPABILITIES = [
        'subscriptions' => 'billing.recurring',
        'employees' => 'payroll.core',
        'payroll-runs' => 'payroll.core',
        'treasury' => 'treasury.accounts',
        'finance-summary' => 'treasury.accounts',
        'bank-reconciliation' => 'treasury.bank_reconciliation',
        'fixed-assets' => 'fixed_assets.tracking',
        'expense-reports' => 'expense_reports.core',
        'financial-reports' => 'reporting.advanced',
        'accounting' => 'accounting.general_ledger',
        'ple' => 'reporting.advanced',
    ];

    public function capabilityFor(string $path): ?string
    {
        foreach (self::ROUTE_CAPABILITIES as $route => $capability) {
            if ($path === $route || str_starts_with($path, $route . '/')) {
                return $capability;
            }
        }

        return null;
    }
}
