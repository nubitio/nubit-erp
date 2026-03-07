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
#[AsCronTask('*/30 * * * *', arguments: '--days=2')]
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
            'Start date (Y-m-d). Defaults to N days ago.'
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
            2
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $toRaw = (string) ($input->getOption('to') ?? '');
            $fromRaw = (string) ($input->getOption('from') ?? '');
            $days = max(1, (int) ($input->getOption('days') ?? 2));

            $toDate = $this->parseDate($toRaw, new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')));
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

            $this->deleteRange('branch_daily_summary', $from, $to);
            $this->deleteRange('payment_method_daily_summary', $from, $to);
            $this->deleteRange('product_daily_summary', $from, $to);

            $this->connection->executeStatement($this->branchDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->paymentMethodDailySql(), [$from, $to]);
            $this->connection->executeStatement($this->productDailySql(), [$from, $to]);

            $this->connection->commit();
        } catch (TableNotFoundException) {
            $this->connection->rollBack();
            $io->warning('Skipping tenant: sales summary tables are not yet migrated.');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

        $io->success(sprintf('Sales daily summaries rebuilt for range %s to %s.', $from, $to));

        return Command::SUCCESS;
    }

    private function parseDate(string $raw, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $value = trim($raw);
        if ($value === '') {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid date format: %s (expected Y-m-d).', $value));
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new \InvalidArgumentException(sprintf('Invalid date value: %s (expected valid Y-m-d date).', $value));
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

    private function branchDailySql(): string
    {
        return <<<'SQL'
INSERT INTO branch_daily_summary (
    summary_date, branch_id,
    sales_count, gross_sales, discount_total, tax_total, net_sales,
    refunds_total, cash_difference_total
)
SELECT
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')),
    s.branch_id,
    COUNT(*),
    ROUND(COALESCE(SUM(s.subtotal), 0), 2),
    ROUND(COALESCE(SUM(s.discount_total), 0), 2),
    ROUND(COALESCE(SUM(s.tax_total), 0), 2),
    ROUND(COALESCE(SUM(s.grand_total), 0), 2),
    ROUND(COALESCE(SUM(CASE WHEN s.status IN ('RETURNED', 'PARTIALLY_RETURNED') THEN s.grand_total ELSE 0 END), 0), 2),
    0
FROM sales s
WHERE DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
GROUP BY DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')), s.branch_id
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
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')),
    s.branch_id,
    sp.payment_method_id,
    COUNT(*),
    ROUND(COALESCE(SUM(sp.amount), 0), 2),
    0
FROM sales s
INNER JOIN sale_payments sp ON sp.sale_id = s.id
WHERE DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
GROUP BY DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')), s.branch_id, sp.payment_method_id
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
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')),
    s.branch_id,
    s.warehouse_id,
    si.product_id,
    ROUND(COALESCE(SUM(si.qty), 0), 3),
    ROUND(COALESCE(SUM(si.net_amount), 0), 2),
    ROUND(COALESCE(SUM(si.unit_cost_snapshot * si.qty), 0), 2),
    ROUND(COALESCE(SUM(si.net_amount - (si.unit_cost_snapshot * si.qty)), 0), 2),
    0
FROM sales s
INNER JOIN sale_items si ON si.sale_id = s.id
WHERE DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) BETWEEN ? AND ?
  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
  AND si.product_id IS NOT NULL
  AND s.warehouse_id IS NOT NULL
GROUP BY DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')), s.branch_id, s.warehouse_id, si.product_id
SQL;
    }
}
