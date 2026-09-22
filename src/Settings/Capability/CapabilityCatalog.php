<?php

declare(strict_types=1);

namespace App\Settings\Capability;

use function array_key_exists;

/**
 * Code-defined, versioned catalogue of operational capabilities (NB-4),
 * mirroring the identity permission catalogue.
 *
 * This is the entitlement/capability split from ADR-013 + ADR-018 D3: a
 * capability is what the tenant's install does; `requiresEntitlement` links it
 * to the commercial gate. Integrity (deps resolve, no cycles, entitlement keys
 * and owner contexts are real) is asserted by CapabilityCatalogTest.
 *
 * Bump VERSION on any breaking change to a definition's shape or semantics.
 */
final class CapabilityCatalog
{
    public const int VERSION = 1;

    /**
     * @return array<string, CapabilityDefinition> keyed by CapabilityKey value
     */
    public static function all(): array
    {
        $definitions = [
            new CapabilityDefinition(
                key: CapabilityKey::SalesCore,
                label: 'Ventas',
                ownerContext: 'Sales',
                defaultEnabled: true,
            ),
            new CapabilityDefinition(
                key: CapabilityKey::BillingSunat,
                label: 'Facturación electrónica SUNAT',
                ownerContext: 'Billing',
                defaultEnabled: true,
                configKeys: ['autoInvoice'],
                safeDefaults: ['autoInvoice' => false],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::BillingRecurring,
                label: 'Facturación recurrente',
                ownerContext: 'Billing',
                requiresEntitlement: 'recurring_billing',
                dependsOn: [CapabilityKey::BillingSunat],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::CashSessions,
                label: 'Sesiones de caja',
                ownerContext: 'CashRegister',
                defaultEnabled: true,
                configKeys: ['requireOpenSession'],
                safeDefaults: ['requireOpenSession' => false],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::InventoryStockTracking,
                label: 'Control de stock',
                ownerContext: 'Inventory',
                defaultEnabled: true,
                configKeys: ['negativeStockPolicy'],
                safeDefaults: ['negativeStockPolicy' => 'STRICT'],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::InventoryMultiWarehouse,
                label: 'Multi-almacén',
                ownerContext: 'Inventory',
                requiresEntitlement: 'multi_warehouse',
                dependsOn: [CapabilityKey::InventoryStockTracking],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::InventoryLotTracking,
                label: 'Control de lotes',
                ownerContext: 'Inventory',
                requiresEntitlement: 'lot_tracking',
                dependsOn: [CapabilityKey::InventoryStockTracking],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::InventoryExpiryTracking,
                label: 'Control de vencimientos',
                ownerContext: 'Inventory',
                requiresEntitlement: 'lot_tracking',
                dependsOn: [CapabilityKey::InventoryLotTracking],
                configKeys: ['blockExpired'],
                safeDefaults: ['blockExpired' => true],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::CatalogWeightedItems,
                label: 'Venta por peso',
                ownerContext: 'Catalog',
                configKeys: ['precision'],
                safeDefaults: ['precision' => 3],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::CatalogMultiUnitSales,
                label: 'Venta multi-unidad',
                ownerContext: 'Catalog',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::HardwareBarcodeScanner,
                label: 'Lector de código de barras',
                ownerContext: 'Settings',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::HardwareScale,
                label: 'Balanza',
                ownerContext: 'Settings',
                dependsOn: [CapabilityKey::CatalogWeightedItems],
                configKeys: ['required', 'stableWeightTimeoutMs'],
                safeDefaults: ['required' => false, 'stableWeightTimeoutMs' => 12_000],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::HardwarePrinting,
                label: 'Impresión de comprobantes',
                ownerContext: 'Settings',
                defaultEnabled: true,
                configKeys: ['method'],
                safeDefaults: ['method' => 'WEBUSB'],
            ),
            new CapabilityDefinition(
                key: CapabilityKey::ReportingAdvanced,
                label: 'Reportes avanzados',
                ownerContext: 'Reporting',
                requiresEntitlement: 'advanced_reports',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::ExportsExcel,
                label: 'Exportación a Excel',
                ownerContext: 'Reporting',
                requiresEntitlement: 'excel_export',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::PayrollCore,
                label: 'Planilla',
                ownerContext: 'Payroll',
                requiresEntitlement: 'payroll',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::TreasuryBankReconciliation,
                label: 'Conciliación bancaria',
                ownerContext: 'BankReconciliation',
                requiresEntitlement: 'bank_reconciliation',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::FixedAssetsTracking,
                label: 'Activos fijos',
                ownerContext: 'FixedAssets',
                requiresEntitlement: 'fixed_assets',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::ExpenseReportsCore,
                label: 'Gastos de personal',
                ownerContext: 'ExpenseReports',
                requiresEntitlement: 'expense_reports',
            ),
            new CapabilityDefinition(
                key: CapabilityKey::AccountingGeneralLedger,
                label: 'Contabilidad (libro mayor)',
                ownerContext: 'Accounting',
                requiresEntitlement: 'general_ledger',
            ),
        ];

        $catalog = [];
        foreach ($definitions as $definition) {
            $catalog[$definition->key->value] = $definition;
        }

        return $catalog;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(CapabilityKey|string $key): ?CapabilityDefinition
    {
        return self::all()[$key instanceof CapabilityKey ? $key->value : $key] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
