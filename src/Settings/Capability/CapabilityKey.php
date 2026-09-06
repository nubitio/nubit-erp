<?php

declare(strict_types=1);

namespace App\Settings\Capability;

/**
 * Operational capabilities — the unit of behaviour the code branches on
 * (ADR-018 D3). Distinct from commercial entitlements
 * ({@see \App\ControlPlane\Billing\Feature\FeatureCatalog}): an entitlement says
 * what the plan allows, a capability says what the tenant's install actually does.
 *
 * Every case must have a matching {@see CapabilityCatalog} definition.
 */
enum CapabilityKey: string
{
    case SalesCore = 'sales.core';
    case BillingSunat = 'billing.sunat';
    case BillingRecurring = 'billing.recurring';
    case CashSessions = 'cash.sessions';
    case InventoryStockTracking = 'inventory.stock_tracking';
    case InventoryMultiWarehouse = 'inventory.multi_warehouse';
    case InventoryLotTracking = 'inventory.lot_tracking';
    case InventoryExpiryTracking = 'inventory.expiry_tracking';
    case CatalogWeightedItems = 'catalog.weighted_items';
    case CatalogMultiUnitSales = 'catalog.multi_unit_sales';
    case HardwareBarcodeScanner = 'hardware.barcode_scanner';
    case HardwareScale = 'hardware.scale';
    case HardwarePrinting = 'hardware.printing';
    case ReportingAdvanced = 'reporting.advanced';
    case ExportsExcel = 'exports.excel';
    case PayrollCore = 'payroll.core';
    case TreasuryBankReconciliation = 'treasury.bank_reconciliation';
    case FixedAssetsTracking = 'fixed_assets.tracking';
    case ExpenseReportsCore = 'expense_reports.core';
    case AccountingGeneralLedger = 'accounting.general_ledger';
}
