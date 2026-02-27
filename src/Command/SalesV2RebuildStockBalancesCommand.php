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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sales:v2:rebuild-stock-balances',
    description: 'Rebuild stock_balances from stock_movements ledger.',
)]
#[AsCronTask('*/15 * * * *', arguments: '--parallel=4')]
final class SalesV2RebuildStockBalancesCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // TRUNCATE issues implicit commits on MariaDB; keep this operation non-transactional.
            $this->connection->executeStatement('DELETE FROM stock_balances');

            $inserted = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO stock_balances (
    warehouse_id,
    product_id,
    variant_id,
    on_hand_qty,
    reserved_qty,
    available_qty,
    avg_cost,
    last_movement_at,
    updated_at
)
SELECT
    sm.warehouse_id,
    sm.product_id,
    COALESCE(sm.variant_id, '') AS variant_id,
    ROUND(COALESCE(SUM(sm.quantity_delta), 0), 3) AS on_hand_qty,
    0 AS reserved_qty,
    ROUND(COALESCE(SUM(sm.quantity_delta), 0), 3) AS available_qty,
    ROUND(
        CASE
            WHEN COALESCE(SUM(CASE WHEN sm.quantity_delta > 0 THEN sm.quantity_delta ELSE 0 END), 0) = 0 THEN NULL
            ELSE COALESCE(SUM(CASE WHEN sm.quantity_delta > 0 THEN sm.total_cost ELSE 0 END), 0)
                 / SUM(CASE WHEN sm.quantity_delta > 0 THEN sm.quantity_delta ELSE 0 END)
        END,
        4
    ) AS avg_cost,
    MAX(sm.occurred_at) AS last_movement_at,
    NOW() AS updated_at
FROM stock_movements sm
GROUP BY
    sm.warehouse_id,
    sm.product_id,
    COALESCE(sm.variant_id, '')
SQL
            );
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: stock_movements/stock_balances tables are not yet migrated.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('stock_balances rebuilt (%d rows).', $inserted));

        return Command::SUCCESS;
    }
}
