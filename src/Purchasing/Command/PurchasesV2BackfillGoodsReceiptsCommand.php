<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
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
    name: 'app:purchases:v2:backfill-goods-receipts',
    description: 'Backfill legacy purchase movements (tipo_id=1) into Purchases V2 goods receipts tables.',
)]
final class PurchasesV2BackfillGoodsReceiptsCommand extends PerTenantCommand
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
            'Delete existing goods_receipt_items/goods_receipts rows before rebuilding.',
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reset = (bool) $input->getOption('reset');

        try {
            $this->connection->fetchOne('SELECT 1 FROM goods_receipts LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM goods_receipt_items LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento_detalle LIMIT 1');
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: required purchases V2 or legacy tables are not yet migrated.');

            return Command::SUCCESS;
        }

        $this->connection->beginTransaction();
        try {
            if ($reset) {
                $this->connection->executeStatement('DELETE FROM goods_receipt_items');
                $this->connection->executeStatement('DELETE FROM goods_receipts');
            }

            $headerInserted = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO goods_receipts (
    id,
    legacy_movimiento_id,
    branch_id,
    warehouse_id,
    supplier_id,
    buyer_user_id,
    status,
    currency,
    exchange_rate,
    subtotal,
    tax_total,
    grand_total,
    note,
    issued_at,
    created_at,
    updated_at,
    sync_status,
    synced_at,
    version_no,
    last_modified_at
)
SELECT
    m.movimiento_id AS id,
    m.movimiento_id AS legacy_movimiento_id,
    m.branch_id,
    m.almacen_id AS warehouse_id,
    m.proveedor_id AS supplier_id,
    COALESCE(m.created_by, m.updated_by, 1) AS buyer_user_id,
    CASE WHEN m.estado = 0 THEN 'VOIDED' ELSE 'POSTED' END AS status,
    'PEN' AS currency,
    1.000000 AS exchange_rate,
    COALESCE(m.subtotal, m.total, 0) AS subtotal,
    0.00 AS tax_total,
    COALESCE(m.total, 0) AS grand_total,
    m.glosa AS note,
    COALESCE(m.fecha, m.date_created, NOW()) AS issued_at,
    COALESCE(m.date_created, NOW()) AS created_at,
    m.date_updated AS updated_at,
    'SYNCED' AS sync_status,
    NOW() AS synced_at,
    1 AS version_no,
    NOW() AS last_modified_at
FROM movimiento m
WHERE m.tipo_id = 1
  AND m.almacen_id IS NOT NULL
  AND m.branch_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM goods_receipts gr
      WHERE BINARY gr.legacy_movimiento_id = BINARY m.movimiento_id
  )
SQL
            );

            $itemsInserted = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO goods_receipt_items (
    goods_receipt_id,
    legacy_movimiento_detalle_id,
    line_no,
    product_id,
    variant_id,
    sku_snapshot,
    name_snapshot,
    uom_snapshot,
    qty,
    unit_cost,
    gross_amount,
    tax_amount,
    total_amount,
    sync_status,
    synced_at,
    version_no,
    last_modified_at,
    created_at,
    updated_at
)
SELECT
    md.movimiento_id AS goods_receipt_id,
    md.id AS legacy_movimiento_detalle_id,
    ROW_NUMBER() OVER (PARTITION BY md.movimiento_id ORDER BY md.id ASC) AS line_no,
    md.producto_id AS product_id,
    NULL AS variant_id,
    p.codigo AS sku_snapshot,
    COALESCE(p.descripcion, 'Producto') AS name_snapshot,
    um.code AS uom_snapshot,
    COALESCE(md.cantidad, 0) AS qty,
    COALESCE(md.precio_unitario, 0) AS unit_cost,
    COALESCE(md.precio_total, 0) AS gross_amount,
    0.00 AS tax_amount,
    COALESCE(md.precio_total, 0) AS total_amount,
    'SYNCED' AS sync_status,
    NOW() AS synced_at,
    1 AS version_no,
    NOW() AS last_modified_at,
    COALESCE(m.date_created, NOW()) AS created_at,
    m.date_updated AS updated_at
FROM movimiento_detalle md
INNER JOIN movimiento m ON BINARY m.movimiento_id = BINARY md.movimiento_id
INNER JOIN goods_receipts gr ON BINARY gr.legacy_movimiento_id = BINARY m.movimiento_id
LEFT JOIN producto p ON BINARY p.producto_id = BINARY md.producto_id
LEFT JOIN unidad um ON um.unidad_id = p.unidad_id
WHERE m.tipo_id = 1
  AND md.producto_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM goods_receipt_items gri
      WHERE gri.legacy_movimiento_detalle_id = md.id
  )
SQL
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $io->success(sprintf(
            'Goods receipts backfill completed (headers=%d, items=%d).',
            $headerInserted,
            $itemsInserted
        ));

        return Command::SUCCESS;
    }
}
