<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function in_array;
use function is_string;
use function sprintf;

/**
 * NB-14 (#340), ADR-018 §D4: legacy Spanish table names are frozen as-is; every
 * **new** table a migration creates must be `<context>_<name>` (snake, English).
 * This is the "check en revisión de migraciones" the issue asks for — a new
 * migration that adds a bare table like `factura` or `pedido` fails CI.
 *
 * The frozen baseline is every table name already created by a migration on the
 * day this guard landed. It only ever shrinks (a table is dropped) — it must
 * never grow, so adding a name here to make the test pass is a review red flag.
 */
final class NewTableNamingConventionTest extends TestCase
{
    private const string MIGRATIONS = __DIR__ . '/../../migrations';

    /**
     * A new table's first `_`-delimited segment must be one of these. Bounded
     * context slugs (NB-13) plus the domain prefixes already established on
     * pre-existing tables (`cash_*`, `bank_*`, `stock_*`, …) so new tables stay
     * consistent with their neighbours.
     *
     * @var list<string>
     */
    private const array ALLOWED_PREFIXES = [
        // bounded contexts
        'accounting',
        'ai',
        'assistant',
        'banking',
        'billing',
        'cashregister',
        'catalog',
        'contacts',
        'controlplane',
        'efact',
        'expensereports',
        'fixedassets',
        'hospitality',
        'identity',
        'integration',
        'inventory',
        'onboarding',
        'organization',
        'payroll',
        'pharmacy',
        'purchasing',
        'reporting',
        'restaurant',
        'sales',
        'settings',
        'taxes',
        'treasury',
        // established domain prefixes on existing tables
        'afp',
        'asset',
        'bank',
        'branch',
        'cash',
        'cashier',
        'chart',
        'config',
        'customer',
        'despatch',
        'employee',
        'entity',
        'exchange',
        'expense',
        'fixed',
        'goods',
        'journal',
        'local',
        'media',
        'menu',
        'messenger',
        'nubit',
        'payment',
        'payslip',
        'price',
        'product',
        'purchase',
        'refresh',
        'refund',
        'retention',
        'return',
        'role',
        'sale',
        'stock',
        'subscription',
        'sunat',
        'sync',
        'tax',
        'tenant',
        'time',
        'tipo',
        'transfer',
        'user',
    ];

    /**
     * Table names created by a migration before NB-14. Frozen — see class docblock.
     *
     * @var list<string>
     */
    private const array LEGACY_BASELINE = [
        'afp_rate',
        'almacen',
        'asset_depreciation_entry',
        'assistant_proposed_action',
        'assistant_thread',
        'assistant_thread_message',
        'audit_log',
        'bank_account',
        'bank_reconciliation_match',
        'bank_statement_import',
        'bank_statement_line',
        'branch_daily_summary',
        'cash_counts',
        'cash_movement',
        'cash_movement_category',
        'cash_movements',
        'cash_reconciliations',
        'cash_register',
        'cash_sessions',
        'cash_shift',
        'cashier_daily_summary',
        'categoria',
        'chart_of_accounts',
        'cliente',
        'comprobante',
        'config',
        'conflict_resolutions',
        'customer_subscription',
        'customer_subscription_line',
        'customer_subscription_run',
        'despatch_guide',
        'despatch_guide_line',
        'employee',
        'empresa',
        'entity_versions',
        'exchange_rates',
        'expense_category',
        'expense_report',
        'fixed_asset',
        'goods_receipt_items',
        'goods_receipts',
        'integration_api_client',
        'integration_api_key',
        'integration_document',
        'integration_event',
        'integration_webhook_delivery',
        'integration_webhook_endpoint',
        'inventory_movement_items',
        'inventory_movements',
        'inventory_reconciliation_runs',
        'journal_entry',
        'journal_entry_line',
        'local',
        'local_comprobante',
        'media',
        'menu',
        'menu_rol',
        'messenger_messages',
        'movimiento',
        'movimiento_detalle',
        'nota',
        'nubit_audit_log',
        'nubit_refresh_token',
        'operacion',
        'payment_method',
        'payment_method_daily_summary',
        'payroll_run',
        'payslip_entry',
        'pharmacy_batch_movements',
        'pharmacy_batches',
        'pharmacy_prescriptions',
        'pharmacy_products',
        'price_history',
        'price_list',
        'price_list_detail',
        'product_bom_items',
        'product_daily_summary',
        'product_stock',
        'product_units',
        'producto',
        'proveedor',
        'purchase_order_items',
        'purchase_order_sequences',
        'purchase_orders',
        'purchase_product_daily_summary',
        'purchase_receipt_sequences',
        'purchases_daily_summary',
        'refresh_token',
        'refund_payments',
        'retention_percepcion',
        'return_items',
        'returns',
        'rol',
        'role_permission',
        'sale_discounts',
        'sale_items',
        'sale_payments',
        'sale_quote_items',
        'sale_quotes',
        'sale_sequences',
        'sale_taxes',
        'sales',
        'sales_daily_summary',
        'stock_adjustments',
        'stock_balances',
        'stock_entries',
        'stock_entry_items',
        'stock_items',
        'stock_lots',
        'stock_movements',
        'stock_transfer_items',
        'stock_transfers',
        'subscription_plan',
        'summary',
        'sunat_catalog',
        'sunat_operational_state',
        'supplier_bill',
        'supplier_payment',
        'sync_inbox',
        'sync_outbox_server',
        'tax_document',
        'tax_document_event',
        'tax_document_line',
        'tenant_capabilities',
        'tenant_inventory_policy',
        'tenant_profile',
        'tenant_settings',
        'time_dimension',
        'tipo_comprobante',
        'transfer_items',
        'transfers',
        'ubigeo',
        'unidad',
        'user_notification',
        'usuario',
    ];

    public function testEveryNewMigrationTableFollowsTheContextPrefixConvention(): void
    {
        $violations = [];
        foreach ($this->createdTables() as $table) {
            if (in_array($table, self::LEGACY_BASELINE, strict: true)) {
                continue;
            }

            $prefix = explode('_', $table)[0];
            if (!in_array($prefix, self::ALLOWED_PREFIXES, strict: true)) {
                $violations[] = sprintf('%s (prefix "%s" is not a known context)', $table, $prefix);
            }
        }

        sort($violations);

        static::assertSame(
            [],
            $violations,
            'New tables must be named `<context>_<name>` (ADR-018 §D4). Rename, or if the prefix is a '
                . 'legitimate new context, add it to ALLOWED_PREFIXES: '
                . implode('; ', $violations),
        );
    }

    public function testLegacyBaselineOnlyContainsTablesThatStillExistInMigrations(): void
    {
        $created = $this->createdTables();
        $stale = array_values(array_filter(
            self::LEGACY_BASELINE,
            static fn(string $table): bool => !in_array($table, $created, strict: true),
        ));
        sort($stale);

        static::assertSame(
            [],
            $stale,
            'LEGACY_BASELINE names a table no migration creates any more — drop it from the list: '
                . implode(', ', $stale),
        );
    }

    /**
     * Lowercased table names from every `CREATE TABLE [IF NOT EXISTS] x` in migrations/.
     *
     * @return list<string>
     */
    private function createdTables(): array
    {
        $names = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            self::MIGRATIONS,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME,
        ));

        foreach ($files as $file) {
            if (!is_string($file) || !str_ends_with($file, '.php')) {
                continue;
            }

            $matches = [];
            preg_match_all(
                '/CREATE TABLE (?:IF NOT EXISTS )?[`\'"]?([a-z_][a-z0-9_]*)/i',
                (string) file_get_contents($file),
                $matches,
            );
            foreach ($matches[1] as $name) {
                $names[strtolower($name)] = true;
            }
        }

        $list = array_keys($names);
        sort($list);

        return $list;
    }
}
