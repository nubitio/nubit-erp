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
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:purchases:v2:rebuild-daily-summaries',
    description: 'Rebuild Purchases V2 daily summary tables for a date range.',
)]
#[AsCronTask('20 * * * *', arguments: '--days=2 --parallel=4')]
final class PurchasesV2RebuildDailySummariesCommand extends TenantAwareCommand
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
            'from',
            null,
            InputOption::VALUE_OPTIONAL,
            'Start date (Y-m-d). Defaults to 30 days ago.'
        );
        $this->addOption(
            'to',
            null,
            InputOption::VALUE_OPTIONAL,
            'End date (Y-m-d). Defaults to today.'
        );
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_OPTIONAL,
            'Rolling window in days when --from/--to are not provided.',
            30
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $toRaw = (string) ($input->getOption('to') ?? '');
            $fromRaw = (string) ($input->getOption('from') ?? '');
            $days = max(1, (int) ($input->getOption('days') ?? 30));

            $toDate = $this->parseDate($toRaw, new \DateTimeImmutable('today'));
            $fromDefault = $toDate->modify(sprintf('-%d days', $days - 1));
            $fromDate = $this->parseDate($fromRaw, $fromDefault);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        if ($fromDate > $toDate) {
            $io->error('Invalid range: --from cannot be after --to.');
            return Command::FAILURE;
        }

        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        try {
            $this->connection->beginTransaction();

            $this->deleteRange('purchases_daily_summary', $from, $to);
            $this->deleteRange('purchase_product_daily_summary', $from, $to);

            $this->connection->executeStatement($this->purchasesDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->purchaseProductDailySql(), [$from, $to]);

            $this->connection->commit();
        } catch (TableNotFoundException) {
            $this->connection->rollBack();
            $io->warning('Skipping tenant: purchases summary tables are not yet migrated.');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

        $io->success(sprintf('Purchases daily summaries rebuilt for range %s to %s.', $from, $to));

        return Command::SUCCESS;
    }

    private function parseDate(string $raw, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $value = trim($raw);
        if ($value === '') {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid date format: %s (expected Y-m-d).', $value));
        }

        return $date;
    }

    private function deleteRange(string $table, string $from, string $to): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE summary_date BETWEEN :from AND :to', $table),
            ['from' => $from, 'to' => $to]
        );
    }

    private function purchasesDailySql(): string
    {
        return <<<'SQL'
INSERT INTO purchases_daily_summary (
    summary_date, branch_id, warehouse_id,
    receipts_count, suppliers_count, items_qty,
    subtotal, tax_total, grand_total
)
SELECT
    DATE(gr.issued_at) AS summary_date,
    gr.branch_id,
    gr.warehouse_id,
    COUNT(*) AS receipts_count,
    COUNT(DISTINCT gr.supplier_id) AS suppliers_count,
    ROUND(COALESCE(SUM(gri.qty), 0), 3) AS items_qty,
    ROUND(COALESCE(SUM(gr.subtotal), 0), 2) AS subtotal,
    ROUND(COALESCE(SUM(gr.tax_total), 0), 2) AS tax_total,
    ROUND(COALESCE(SUM(gr.grand_total), 0), 2) AS grand_total
FROM goods_receipts gr
LEFT JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
WHERE DATE(gr.issued_at) BETWEEN ? AND ?
  AND gr.status = 'POSTED'
  AND gr.deleted_at IS NULL
GROUP BY DATE(gr.issued_at), gr.branch_id, gr.warehouse_id
SQL;
    }

    private function purchaseProductDailySql(): string
    {
        return <<<'SQL'
INSERT INTO purchase_product_daily_summary (
    summary_date, branch_id, warehouse_id, product_id,
    receipts_count, qty_received, subtotal, tax_total, total_amount
)
SELECT
    DATE(gr.issued_at) AS summary_date,
    gr.branch_id,
    gr.warehouse_id,
    gri.product_id,
    COUNT(DISTINCT gr.id) AS receipts_count,
    ROUND(COALESCE(SUM(gri.qty), 0), 3) AS qty_received,
    ROUND(COALESCE(SUM(gri.gross_amount), 0), 2) AS subtotal,
    ROUND(COALESCE(SUM(gri.tax_amount), 0), 2) AS tax_total,
    ROUND(COALESCE(SUM(gri.total_amount), 0), 2) AS total_amount
FROM goods_receipts gr
INNER JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
WHERE DATE(gr.issued_at) BETWEEN ? AND ?
  AND gr.status = 'POSTED'
  AND gr.deleted_at IS NULL
GROUP BY DATE(gr.issued_at), gr.branch_id, gr.warehouse_id, gri.product_id
SQL;
    }
}
