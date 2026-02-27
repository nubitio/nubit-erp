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
    name: 'app:sales:v2:rebuild-daily-summaries',
    description: 'Rebuild Sales V2 daily summary tables for a date range.',
)]
#[AsCronTask('10 * * * *', arguments: '--days=2 --parallel=4')]
final class SalesV2RebuildDailySummariesCommand extends TenantAwareCommand
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

            $this->deleteRange('sales_daily_summary', $from, $to);
            $this->deleteRange('product_daily_summary', $from, $to);
            $this->deleteRange('cashier_daily_summary', $from, $to);
            $this->deleteRange('branch_daily_summary', $from, $to);
            $this->deleteRange('payment_method_daily_summary', $from, $to);

            $this->connection->executeStatement($this->salesDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->productDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->cashierDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->branchDailySql(), [$from, $to, $from, $to]);
            $this->connection->executeStatement($this->paymentMethodDailySql(), [$from, $to]);

            $this->connection->commit();
        } catch (TableNotFoundException $exception) {
            $this->connection->rollBack();
            $io->warning('Skipping tenant: summary tables are not yet migrated.');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

        $io->success(sprintf('Daily summaries rebuilt for range %s to %s.', $from, $to));

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

    private function salesDailySql(): string
    {
        return <<<'SQL'
INSERT INTO sales_daily_summary (
    summary_date, branch_id, warehouse_id,
    sales_count, gross_sales, discount_total, tax_total, net_sales, avg_ticket, items_qty
)
SELECT
    DATE(s.issued_at) AS summary_date,
    s.branch_id,
    s.warehouse_id,
    COUNT(*) AS sales_count,
    ROUND(COALESCE(SUM(s.subtotal), 0), 2) AS gross_sales,
    ROUND(COALESCE(SUM(s.discount_total), 0), 2) AS discount_total,
    ROUND(COALESCE(SUM(s.tax_total), 0), 2) AS tax_total,
    ROUND(COALESCE(SUM(s.grand_total), 0), 2) AS net_sales,
    ROUND(COALESCE(AVG(s.grand_total), 0), 2) AS avg_ticket,
    ROUND(COALESCE(SUM(si.qty), 0), 3) AS items_qty
FROM sales s
LEFT JOIN sale_items si ON si.sale_id = s.id
WHERE DATE(s.issued_at) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
GROUP BY DATE(s.issued_at), s.branch_id, s.warehouse_id
SQL;
    }

    private function productDailySql(): string
    {
        return <<<'SQL'
INSERT INTO product_daily_summary (
    summary_date, branch_id, warehouse_id, product_id,
    qty_sold, net_sales, cost_total, margin_total, returns_qty
)
SELECT
    DATE(s.issued_at) AS summary_date,
    s.branch_id,
    s.warehouse_id,
    si.product_id,
    ROUND(COALESCE(SUM(si.qty), 0), 3) AS qty_sold,
    ROUND(COALESCE(SUM(si.net_amount), 0), 2) AS net_sales,
    ROUND(COALESCE(SUM(COALESCE(si.unit_cost_snapshot, 0) * si.qty), 0), 2) AS cost_total,
    ROUND(COALESCE(SUM(COALESCE(si.margin_amount, 0)), 0), 2) AS margin_total,
    ROUND(COALESCE(SUM(ri.qty_returned), 0), 3) AS returns_qty
FROM sales s
INNER JOIN sale_items si ON si.sale_id = s.id
LEFT JOIN return_items ri ON ri.sale_item_id = si.id
LEFT JOIN returns r ON r.id = ri.return_id
WHERE DATE(s.issued_at) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
  AND (r.id IS NULL OR r.status = 'COMPLETED')
GROUP BY DATE(s.issued_at), s.branch_id, s.warehouse_id, si.product_id
SQL;
    }

    private function cashierDailySql(): string
    {
        return <<<'SQL'
INSERT INTO cashier_daily_summary (
    summary_date, branch_id, cashier_user_id, cash_session_id,
    sales_count, collected_total, refund_total, cash_difference
)
SELECT
    DATE(s.issued_at) AS summary_date,
    s.branch_id,
    s.seller_user_id AS cashier_user_id,
    cs.id AS cash_session_id,
    COUNT(*) AS sales_count,
    ROUND(COALESCE(SUM(s.paid_total), 0), 2) AS collected_total,
    ROUND(COALESCE(SUM(r.refund_total), 0), 2) AS refund_total,
    ROUND(COALESCE(MAX(cs.difference_amount), 0), 2) AS cash_difference
FROM sales s
LEFT JOIN returns r ON r.sale_id = s.id AND r.status = 'COMPLETED'
INNER JOIN cash_sessions cs
    ON cs.id = s.cash_session_id
    OR cs.legacy_cash_shift_id = s.cash_session_id
WHERE DATE(s.issued_at) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
GROUP BY DATE(s.issued_at), s.branch_id, s.seller_user_id, cs.id
SQL;
    }

    private function branchDailySql(): string
    {
        return <<<'SQL'
INSERT INTO branch_daily_summary (
    summary_date, branch_id,
    sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total, cash_difference_total
)
SELECT
    base.summary_date,
    base.branch_id,
    base.sales_count,
    base.gross_sales,
    base.discount_total,
    base.tax_total,
    base.net_sales,
    COALESCE(refunds.refunds_total, 0) AS refunds_total,
    COALESCE(cash_diff.cash_difference_total, 0) AS cash_difference_total
FROM (
    SELECT
        DATE(s.issued_at) AS summary_date,
        s.branch_id,
        COUNT(*) AS sales_count,
        ROUND(COALESCE(SUM(s.subtotal), 0), 2) AS gross_sales,
        ROUND(COALESCE(SUM(s.discount_total), 0), 2) AS discount_total,
        ROUND(COALESCE(SUM(s.tax_total), 0), 2) AS tax_total,
        ROUND(COALESCE(SUM(s.grand_total), 0), 2) AS net_sales
    FROM sales s
    WHERE DATE(s.issued_at) BETWEEN ? AND ?
      AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    GROUP BY DATE(s.issued_at), s.branch_id
) base
LEFT JOIN (
    SELECT
        DATE(r.created_at) AS summary_date,
        r.branch_id,
        ROUND(COALESCE(SUM(r.refund_total), 0), 2) AS refunds_total
    FROM returns r
    WHERE DATE(r.created_at) BETWEEN ? AND ?
      AND r.status = 'COMPLETED'
    GROUP BY DATE(r.created_at), r.branch_id
) refunds ON refunds.summary_date = base.summary_date AND refunds.branch_id = base.branch_id
LEFT JOIN (
    SELECT
        DATE(cs.closed_at) AS summary_date,
        cs.branch_id,
        ROUND(COALESCE(SUM(cs.difference_amount), 0), 2) AS cash_difference_total
    FROM cash_sessions cs
    WHERE cs.closed_at IS NOT NULL
    GROUP BY DATE(cs.closed_at), cs.branch_id
) cash_diff ON cash_diff.summary_date = base.summary_date AND cash_diff.branch_id = base.branch_id
SQL;
    }

    private function paymentMethodDailySql(): string
    {
        return <<<'SQL'
INSERT INTO payment_method_daily_summary (
    summary_date, branch_id, payment_method_id,
    payment_count, amount_total, refund_amount_total
)
SELECT
    DATE(s.issued_at) AS summary_date,
    s.branch_id,
    sp.payment_method_id,
    COUNT(*) AS payment_count,
    ROUND(COALESCE(SUM(sp.amount), 0), 2) AS amount_total,
    ROUND(COALESCE(SUM(rp.amount), 0), 2) AS refund_amount_total
FROM sale_payments sp
INNER JOIN sales s ON s.id = sp.sale_id
LEFT JOIN returns r ON r.sale_id = s.id AND r.status = 'COMPLETED'
LEFT JOIN refund_payments rp ON rp.return_id = r.id AND rp.payment_method_id = sp.payment_method_id
WHERE DATE(s.issued_at) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
GROUP BY DATE(s.issued_at), s.branch_id, sp.payment_method_id
SQL;
    }
}
