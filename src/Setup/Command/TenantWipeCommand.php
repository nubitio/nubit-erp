<?php

declare(strict_types=1);

namespace App\Setup\Command;

use Doctrine\DBAL\Connection;
use Nubit\Platform\Symfony\Console\TenantAwareCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Wipes all transactional data from a tenant database so they can start
 * using the system for real operations after a trial / demo period.
 *
 * ## What gets deleted
 *
 * Transactional (always):
 *   - Sales, sale items, payments, taxes, discounts
 *   - Tax documents (SUNAT invoices) and their events/lines
 *   - Cash sessions, cash movements, cash counts, cash shifts
 *   - Purchases (goods receipts) and their items
 *   - Stock movements, stock entries/transfers and their items
 *   - Stock lots, stock items, stock balances, reconciliation runs
 *   - Returns, return items, refund payments
 *   - Sale sequences (reset to 0 — numbering restarts from 1)
 *   - All daily summaries and sync tables
 *   - User notifications, audit log
 *
 * Stock levels:
 *   - product_stock quantities reset to 0
 *
 * ## What is KEPT by default (configuration + catalog)
 *   Company, branches, warehouses, users, roles
 *   Categories, products, customers, suppliers
 *   Payment methods, voucher types/series, price lists
 *   SUNAT catalog, units of measure, config settings
 *
 * ## --wipe-catalog flag
 *   Also deletes products, customers, suppliers and resets price lists
 *   so the tenant can re-enter their real catalog from scratch.
 *
 * Usage:
 *   php bin/console app:tenant:wipe --tenant=jawka --force
 *   php bin/console app:tenant:wipe --tenant=jawka --wipe-catalog --force
 */
#[AsCommand(
    name: 'app:tenant:wipe',
    description: 'Wipe all transactional data from a tenant DB (preserves config & catalog by default).',
)]
final class TenantWipeCommand extends TenantAwareCommand
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation prompt')
             ->addOption('wipe-catalog', null, InputOption::VALUE_NONE, 'Also delete products, customers and suppliers');
    }

    #[\Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io           = new SymfonyStyle($input, $output);
        $wipeCatalog  = (bool) $input->getOption('wipe-catalog');
        $force        = (bool) $input->getOption('force');

        // ---- Confirmation ------------------------------------------------
        $scope = $wipeCatalog
            ? 'transacciones + catálogo (productos, clientes, proveedores)'
            : 'transacciones (ventas, compras, cajas, documentos)';

        $io->warning([
            '⚠️  Este comando es IRREVERSIBLE.',
            "Alcance: {$scope}",
            'Tenant actual: ' . ($this->currentTenantName ?? '(default)'),
        ]);

        if (!$force && !$io->confirm('¿Confirmas que quieres continuar?', false)) {
            $io->note('Operación cancelada.');

            return self::SUCCESS;
        }

        // ---- Execute ------------------------------------------------------
        $io->text('Desactivando foreign key checks…');
        $this->connection->executeStatement('SET foreign_key_checks = 0');

        try {
            $io->section('Borrando datos transaccionales…');
            $this->wipeTransactions($io);

            if ($wipeCatalog) {
                $io->section('Borrando catálogo…');
                $this->wipeCatalog($io);
            }

            $io->section('Reseteando stock a 0…');
            $affected = $this->connection->executeStatement('UPDATE product_stock SET stock = 0, updated_at = NOW()');
            $io->text("  product_stock: {$affected} filas actualizadas");
        } finally {
            $this->connection->executeStatement('SET foreign_key_checks = 1');
        }

        $io->success('Tenant limpiado correctamente. Listo para operaciones reales. 🎉');

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function wipeTransactions(SymfonyStyle $io): void
    {
        $tables = [
            // — Summaries & derived (no deps) —
            'sales_daily_summary',
            'branch_daily_summary',
            'payment_method_daily_summary',
            'product_daily_summary',
            'cashier_daily_summary',
            'purchases_daily_summary',
            'purchase_product_daily_summary',
            'summary',

            // — Sync / audit —
            'sync_inbox',
            'sync_outbox_server',
            'conflict_resolutions',
            'entity_versions',
            'user_notification',
            'audit_log',

            // — Inventory detail —
            'stock_lots',
            'stock_items',
            'stock_balances',
            'stock_movements',
            'stock_entry_items',
            'stock_entries',
            'stock_transfer_items',
            'stock_transfers',
            'inventory_reconciliation_runs',

            // — Sales detail —
            'cash_counts',
            'cash_shift',
            'cash_movements',
            'sale_discounts',
            'sale_taxes',
            'sale_payments',
            'sale_items',
            'refund_payments',
            'return_items',
            'returns',
            'price_history',

            // — Tax documents —
            'tax_document_event',
            'tax_document_line',
            'tax_document',

            // — Purchase headers —
            'goods_receipt_items',
            'goods_receipts',

            // — Cash sessions —
            'cash_sessions',

            // — Sale headers —
            'sales',
        ];

        foreach ($tables as $table) {
            $n = $this->truncateIfExists($table);
            if ($n !== null) {
                $io->text("  {$table}: {$n} filas eliminadas");
            }
        }

        // Reset sale sequences without dropping the rows (needed for FK integrity)
        $n = $this->connection->executeStatement('UPDATE sale_sequences SET last_number = 0');
        $io->text("  sale_sequences: {$n} series reseteadas a 0");

        // Reset voucher series counters stored in local_comprobante
        $n = $this->connection->executeStatement('DELETE FROM local_comprobante');
        $io->text("  local_comprobante: {$n} filas eliminadas");
    }

    private function wipeCatalog(SymfonyStyle $io): void
    {
        $tables = [
            'product_bom_items',
            'product_stock',
            'producto',
            'price_list',
            'cliente',
            'proveedor',
        ];

        foreach ($tables as $table) {
            $n = $this->truncateIfExists($table);
            if ($n !== null) {
                $io->text("  {$table}: {$n} filas eliminadas");
            }
        }
    }

    /**
     * Truncate a table if it exists. Returns the row count deleted or null if
     * the table does not exist (graceful for optional tables).
     */
    private function truncateIfExists(string $table): ?int
    {
        try {
            $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM `{$table}`");
            $this->connection->executeStatement("TRUNCATE TABLE `{$table}`");

            return $count;
        } catch (\Throwable) {
            return null;
        }
    }
}
