<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:backfill',
    description: 'Backfill legacy sale movements (tipo_id=2) from movimiento into Sales V2 tables (sales, sale_items).',
)]
final class SalesV2BackfillCommand extends TenantAwareCommand
{
    private const int CHUNK_SIZE = 1000;

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
            'Delete existing sale_items/sales rows before rebuilding.',
        );
        $this->addOption(
            'chunk-size',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of movimiento rows processed per batch.',
            self::CHUNK_SIZE,
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reset     = (bool) $input->getOption('reset');
        $chunkSize = max(1, (int) $input->getOption('chunk-size'));

        try {
            $this->connection->fetchOne('SELECT 1 FROM sales LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM sale_items LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento_detalle LIMIT 1');
        } catch (TableNotFoundException) {
            $io->warning('Skipping tenant: required Sales V2 or legacy tables are not yet migrated.');

            return Command::SUCCESS;
        }

        if ($reset) {
            $io->comment('Resetting existing sale_items and sales rows…');
            $this->connection->executeStatement('DELETE FROM sale_items');
            $this->connection->executeStatement('DELETE FROM sales');
        }

        // Collect all eligible movimiento_ids ordered so we can cursor through them in chunks.
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT movimiento_id FROM movimiento'
            . ' WHERE tipo_id = 2 AND almacen_id IS NOT NULL AND branch_id IS NOT NULL'
            . ' ORDER BY movimiento_id',
        );

        $total = count($ids);

        if ($total === 0) {
            $io->success('Nothing to backfill.');

            return Command::SUCCESS;
        }

        $io->comment(sprintf('Backfilling %d movimiento rows in chunks of %d…', $total, $chunkSize));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — headers: %headers% / items: %items%');
        $progressBar->setMessage('0', 'headers');
        $progressBar->setMessage('0', 'items');
        $progressBar->start();

        $headersInserted = 0;
        $itemsInserted   = 0;
        $chunks          = array_chunk($ids, $chunkSize);

        foreach ($chunks as $chunk) {
            $firstId = reset($chunk);
            $lastId  = end($chunk);

            $this->connection->beginTransaction();
            try {
                $h = (int) $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sales (
    id,
    legacy_movimiento_id,
    branch_id,
    warehouse_id,
    cash_register_id,
    cash_session_id,
    customer_id,
    seller_user_id,
    status,
    currency,
    exchange_rate,
    subtotal,
    discount_total,
    tax_total,
    grand_total,
    paid_total,
    change_total,
    balance_due,
    issued_at,
    created_at,
    updated_at,
    sync_status,
    synced_at,
    local_uuid,
    idempotency_key,
    version_no,
    last_modified_at
)
SELECT
    m.movimiento_id                                       AS id,
    m.movimiento_id                                       AS legacy_movimiento_id,
    m.branch_id,
    m.almacen_id                                          AS warehouse_id,
    m.caja_id                                             AS cash_register_id,
    (
        SELECT cs.id
        FROM cash_sessions cs
        WHERE cs.cash_register_id = m.caja_id
          AND cs.branch_id        = m.branch_id
          AND cs.opened_at        <= COALESCE(m.fecha, m.date_created, NOW())
          AND (
              cs.closed_at IS NULL
              OR cs.closed_at >= COALESCE(m.fecha, m.date_created, NOW())
          )
        ORDER BY cs.opened_at DESC
        LIMIT 1
    )                                                     AS cash_session_id,
    m.cliente_id                                          AS customer_id,
    COALESCE(m.created_by, m.updated_by, 1)              AS seller_user_id,
    CASE WHEN m.estado = 0 THEN 'VOIDED' ELSE 'COMPLETED' END AS status,
    'PEN'                                                 AS currency,
    1.000000                                              AS exchange_rate,
    COALESCE(m.subtotal, m.total, 0)                      AS subtotal,
    COALESCE(
        CASE
            WHEN m.descuento IS NOT NULL AND m.descuento > 0
                THEN ROUND(COALESCE(m.subtotal, m.total, 0) * m.descuento / 100, 2)
            ELSE 0
        END,
        0
    )                                                     AS discount_total,
    0.00                                                  AS tax_total,
    COALESCE(m.total, 0)                                  AS grand_total,
    COALESCE(m.total, 0)                                  AS paid_total,
    0.00                                                  AS change_total,
    0.00                                                  AS balance_due,
    COALESCE(m.fecha, m.date_created, NOW())              AS issued_at,
    COALESCE(m.date_created, NOW())                       AS created_at,
    m.date_updated                                        AS updated_at,
    'SYNCED'                                              AS sync_status,
    NOW()                                                 AS synced_at,
    LOWER(
        CONCAT(
            SUBSTR(MD5(CONCAT('local:sale:', m.movimiento_id)), 1, 8), '-',
            SUBSTR(MD5(CONCAT('local:sale:', m.movimiento_id)), 9, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale:', m.movimiento_id)), 13, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale:', m.movimiento_id)), 17, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale:', m.movimiento_id)), 21, 12)
        )
    )                                                     AS local_uuid,
    LOWER(
        CONCAT(
            SUBSTR(MD5(CONCAT('idem:sale:', m.movimiento_id)), 1, 8), '-',
            SUBSTR(MD5(CONCAT('idem:sale:', m.movimiento_id)), 9, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale:', m.movimiento_id)), 13, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale:', m.movimiento_id)), 17, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale:', m.movimiento_id)), 21, 12)
        )
    )                                                     AS idempotency_key,
    1                                                     AS version_no,
    NOW()                                                 AS last_modified_at
FROM movimiento m
LEFT JOIN sales s_existing ON s_existing.legacy_movimiento_id = m.movimiento_id
WHERE m.movimiento_id >= :firstId
  AND m.movimiento_id <= :lastId
  AND m.tipo_id = 2
  AND s_existing.id IS NULL
SQL,
                    ['firstId' => $firstId, 'lastId' => $lastId]
                );

                $i = (int) $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sale_items (
    sale_id,
    legacy_movimiento_detalle_id,
    line_no,
    product_id,
    variant_id,
    sku_snapshot,
    name_snapshot,
    uom_snapshot,
    qty,
    unit_price,
    gross_amount,
    discount_amount,
    tax_amount,
    net_amount,
    unit_cost_snapshot,
    margin_amount,
    created_at,
    updated_at,
    sync_status,
    synced_at,
    version_no,
    last_modified_at
)
SELECT
    md.movimiento_id                                      AS sale_id,
    md.id                                                 AS legacy_movimiento_detalle_id,
    ROW_NUMBER() OVER (PARTITION BY md.movimiento_id ORDER BY md.id ASC) AS line_no,
    md.producto_id                                        AS product_id,
    NULL                                                  AS variant_id,
    p.codigo                                              AS sku_snapshot,
    COALESCE(p.descripcion, 'Producto')                   AS name_snapshot,
    um.code                                               AS uom_snapshot,
    COALESCE(md.cantidad, 0)                              AS qty,
    COALESCE(md.precio_unitario, 0)                       AS unit_price,
    COALESCE(md.precio_total, 0)                          AS gross_amount,
    COALESCE(md.descuento, 0)                             AS discount_amount,
    0.00                                                  AS tax_amount,
    COALESCE(md.precio_total, 0)                          AS net_amount,
    p.precio_compra                                       AS unit_cost_snapshot,
    NULL                                                  AS margin_amount,
    COALESCE(m.date_created, NOW())                       AS created_at,
    m.date_updated                                        AS updated_at,
    'SYNCED'                                              AS sync_status,
    NOW()                                                 AS synced_at,
    1                                                     AS version_no,
    NOW()                                                 AS last_modified_at
FROM movimiento_detalle md
INNER JOIN movimiento m       ON m.movimiento_id          = md.movimiento_id
INNER JOIN sales s            ON s.legacy_movimiento_id   = m.movimiento_id
LEFT  JOIN producto p         ON p.producto_id            = md.producto_id
LEFT  JOIN unidad um          ON um.unidad_id             = p.unidad_id
LEFT  JOIN sale_items si_existing ON si_existing.legacy_movimiento_detalle_id = md.id
WHERE m.movimiento_id >= :firstId
  AND m.movimiento_id <= :lastId
  AND m.tipo_id = 2
  AND si_existing.id IS NULL
SQL,
                    ['firstId' => $firstId, 'lastId' => $lastId]
                );

                $this->connection->commit();
            } catch (\Throwable $exception) {
                $this->connection->rollBack();
                throw $exception;
            }

            $headersInserted += $h;
            $itemsInserted   += $i;

            $progressBar->setMessage((string) $headersInserted, 'headers');
            $progressBar->setMessage((string) $itemsInserted, 'items');
            $progressBar->advance(count($chunk));
        }

        $progressBar->finish();
        $output->writeln('');

        $io->success(sprintf(
            'Sales V2 backfill completed (headers=%d, items=%d).',
            $headersInserted,
            $itemsInserted,
        ));

        return Command::SUCCESS;
    }
}
