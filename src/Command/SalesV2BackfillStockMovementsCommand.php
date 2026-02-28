<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:backfill-stock-movements',
    description: 'Backfill stock_movements ledger from Sales V2 sales and legacy purchase movements.',
)]
final class SalesV2BackfillStockMovementsCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'reset',
            null,
            InputOption::VALUE_NONE,
            'Delete previously backfilled SALE_ITEM/LEGACY_PURCHASE_DETAIL records before rebuilding.',
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reset = (bool) $input->getOption('reset');

        try {
            $this->connection->fetchOne('SELECT 1 FROM stock_movements LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM sales LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM sale_items LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento_detalle LIMIT 1');
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: required tables for stock ledger backfill are not yet migrated.');

            return Command::SUCCESS;
        }

        $this->connection->beginTransaction();
        try {
            if ($reset) {
                $this->connection->executeStatement(
                    <<<'SQL'
DELETE FROM stock_movements
WHERE source_type IN ('SALE_ITEM', 'LEGACY_PURCHASE_DETAIL')
SQL
                );
            }

            $saleOutInserted = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO stock_movements (
    warehouse_id,
    product_id,
    variant_id,
    occurred_at,
    movement_type,
    quantity_delta,
    unit_cost,
    total_cost,
    source_type,
    source_id,
    sale_item_id,
    reference_code,
    note,
    sync_status,
    created_at
)
SELECT
    s.warehouse_id,
    si.product_id,
    NULL AS variant_id,
    s.issued_at,
    'SALE_OUT' AS movement_type,
    ROUND(si.qty * -1, 3) AS quantity_delta,
    si.unit_cost_snapshot AS unit_cost,
    CASE
        WHEN si.unit_cost_snapshot IS NULL THEN NULL
        ELSE ROUND(si.unit_cost_snapshot * si.qty, 2)
    END AS total_cost,
    'SALE_ITEM' AS source_type,
    CAST(si.id AS CHAR(64)) AS source_id,
    si.id AS sale_item_id,
    s.legacy_movimiento_id AS reference_code,
    'Backfill SALE_OUT from sale_items' AS note,
    'SYNCED' AS sync_status,
    COALESCE(s.created_at, NOW()) AS created_at
FROM sale_items si
INNER JOIN sales s ON BINARY s.id = BINARY si.sale_id
WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
  AND NOT EXISTS (
      SELECT 1
      FROM stock_movements sm
      WHERE sm.source_type = 'SALE_ITEM'
        AND BINARY sm.source_id = BINARY CAST(si.id AS CHAR(64))
        AND sm.movement_type = 'SALE_OUT'
  )
SQL
            );

            $purchaseInInserted = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO stock_movements (
    warehouse_id,
    product_id,
    variant_id,
    occurred_at,
    movement_type,
    quantity_delta,
    unit_cost,
    total_cost,
    source_type,
    source_id,
    reference_code,
    note,
    sync_status,
    created_at
)
SELECT
    m.almacen_id AS warehouse_id,
    md.producto_id AS product_id,
    NULL AS variant_id,
    COALESCE(m.fecha, m.date_created, NOW()) AS occurred_at,
    'PURCHASE_IN' AS movement_type,
    ROUND(md.cantidad, 3) AS quantity_delta,
    md.precio_unitario AS unit_cost,
    md.precio_total AS total_cost,
    'LEGACY_PURCHASE_DETAIL' AS source_type,
    CAST(md.id AS CHAR(64)) AS source_id,
    m.movimiento_id AS reference_code,
    'Backfill PURCHASE_IN from movimiento_detalle' AS note,
    'SYNCED' AS sync_status,
    COALESCE(m.date_created, NOW()) AS created_at
FROM movimiento_detalle md
INNER JOIN movimiento m ON BINARY m.movimiento_id = BINARY md.movimiento_id
WHERE m.tipo_id = 1
  AND m.estado = 1
  AND m.almacen_id IS NOT NULL
  AND md.producto_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM stock_movements sm
      WHERE sm.source_type = 'LEGACY_PURCHASE_DETAIL'
        AND BINARY sm.source_id = BINARY CAST(md.id AS CHAR(64))
        AND sm.movement_type = 'PURCHASE_IN'
  )
SQL
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $io->success(sprintf(
            'Stock movements backfill completed (sale_out=%d, purchase_in=%d).',
            $saleOutInserted,
            $purchaseInInserted
        ));

        return Command::SUCCESS;
    }
}
