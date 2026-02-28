<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:go-no-go',
    description: 'Evaluate Sales V2 Go/No-Go checklist for the current tenant.',
)]
final class SalesV2GoNoGoCommand extends TenantAwareCommand
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

        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Rolling window in days for reconciliation checks.',
                7
            )
            ->addOption(
                'money-tolerance',
                null,
                InputOption::VALUE_OPTIONAL,
                'Absolute tolerance for monetary differences.',
                '0.01'
            )
            ->addOption(
                'stock-tolerance',
                null,
                InputOption::VALUE_OPTIONAL,
                'Absolute tolerance for stock differences.',
                '0.001'
            )
            ->addOption(
                'json',
                null,
                InputOption::VALUE_NONE,
                'Emit machine-readable JSON summary after the table.'
            );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = max(1, (int) ($input->getOption('days') ?? 7));
        $moneyTolerance = max(0.0, (float) ($input->getOption('money-tolerance') ?? '0.01'));
        $stockTolerance = max(0.0, (float) ($input->getOption('stock-tolerance') ?? '0.001'));

        $fromDate = (new \DateTimeImmutable('today'))
            ->modify(sprintf('-%d days', $days - 1))
            ->format('Y-m-d');
        $fromDateTime = $fromDate . ' 00:00:00';

        $checks = [
            $this->checkDailyMonetaryGap($days, $fromDateTime, $moneyTolerance),
            $this->checkStockGap($stockTolerance),
            $this->checkCashSessionIntegrity($fromDateTime),
            $this->checkPaymentIntegrity($fromDateTime, $moneyTolerance),
            $this->checkDashboardReadModelCoverage($fromDate, $fromDateTime, $moneyTolerance),
        ];

        $rows = [];
        $allGo = true;
        foreach ($checks as $check) {
            $allGo = $allGo && $check['passed'];
            $rows[] = [
                $check['name'],
                $check['passed'] ? 'GO' : 'NO-GO',
                $check['metric'],
                $check['threshold'],
            ];
        }

        $io->table(['Check', 'Status', 'Metric', 'Threshold'], $rows);

        if ($allGo) {
            $io->success(sprintf('GO: tenant passed all checks for last %d day(s).', $days));
        } else {
            $io->error(sprintf('NO-GO: tenant failed one or more checks for last %d day(s).', $days));
        }

        if ((bool) $input->getOption('json')) {
            $payload = [
                'tenant' => $this->currentTenantName ?? 'unknown',
                'window_days' => $days,
                'from_date' => $fromDate,
                'money_tolerance' => $moneyTolerance,
                'stock_tolerance' => $stockTolerance,
                'overall' => $allGo ? 'GO' : 'NO-GO',
                'checks' => $checks,
            ];

            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $io->writeln($json);
            }
        }

        return $allGo ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{name:string,passed:bool,metric:string,threshold:string}
     */
    private function checkDailyMonetaryGap(int $days, string $fromDateTime, float $moneyTolerance): array
    {
        if (!$this->tablesExist(['movimiento', 'sales'])) {
            return [
                'name' => 'Gap monetario diario',
                'passed' => false,
                'metric' => 'tables_missing',
                'threshold' => '0 mismatches',
            ];
        }

        $legacyRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT
    DATE(fecha) AS d,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(total), 0), 2) AS total_amount
FROM movimiento
WHERE tipo_id = 2
  AND fecha >= ?
GROUP BY DATE(fecha)
SQL,
            [$fromDateTime]
        );

        $v2Rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT
    DATE(issued_at) AS d,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(grand_total), 0), 2) AS total_amount
FROM sales
WHERE issued_at >= ?
GROUP BY DATE(issued_at)
SQL,
            [$fromDateTime]
        );

        $legacyMap = $this->toDayMap($legacyRows, 'd');
        $v2Map = $this->toDayMap($v2Rows, 'd');
        $daysToCompare = array_unique(array_merge(array_keys($legacyMap), array_keys($v2Map)));
        sort($daysToCompare);

        $mismatchDays = 0;
        foreach ($daysToCompare as $day) {
            $legacy = $legacyMap[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];
            $v2 = $v2Map[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];
            $countDiff = (int) $legacy['cnt'] - (int) $v2['cnt'];
            $amountDiff = round((float) $legacy['total_amount'] - (float) $v2['total_amount'], 2);
            if ($countDiff !== 0 || abs($amountDiff) > $moneyTolerance) {
                ++$mismatchDays;
            }
        }

        return [
            'name' => 'Gap monetario diario',
            'passed' => $mismatchDays === 0,
            'metric' => sprintf('%d mismatch day(s) / %d day(s)', $mismatchDays, max(1, count($daysToCompare))),
            'threshold' => sprintf('0 mismatches (tol=%.2f)', $moneyTolerance),
        ];
    }

    /**
     * @return array{name:string,passed:bool,metric:string,threshold:string}
     */
    private function checkStockGap(float $stockTolerance): array
    {
        if (!$this->tablesExist(['product_stock', 'stock_balances'])) {
            return [
                'name' => 'Gap de stock',
                'passed' => false,
                'metric' => 'tables_missing',
                'threshold' => '0 diffs',
            ];
        }

        $gapCount = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT
        ps.warehouse_id,
        ps.product_id,
        ABS(ps.stock - COALESCE(sb.on_hand_qty, 0)) AS diff
    FROM product_stock ps
    LEFT JOIN stock_balances sb
      ON sb.warehouse_id = ps.warehouse_id
     AND sb.product_id = ps.product_id
     AND COALESCE(sb.variant_id, '') = ''
) x
WHERE x.diff > :tol
SQL,
            ['tol' => $stockTolerance]
        );

        return [
            'name' => 'Gap de stock',
            'passed' => $gapCount === 0,
            'metric' => sprintf('%d row(s) out of tolerance', $gapCount),
            'threshold' => sprintf('0 rows (tol=%.3f)', $stockTolerance),
        ];
    }

    /**
     * @return array{name:string,passed:bool,metric:string,threshold:string}
     */
    private function checkCashSessionIntegrity(string $fromDateTime): array
    {
        if (!$this->tablesExist(['sales', 'cash_sessions'])) {
            return [
                'name' => 'Integridad sesion de caja',
                'passed' => false,
                'metric' => 'tables_missing',
                'threshold' => '0 invalid + 0 mismatch',
            ];
        }

        $invalidSessions = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM sales s
LEFT JOIN cash_sessions cs ON cs.id = s.cash_session_id
WHERE s.issued_at >= :from_dt
  AND s.cash_session_id IS NOT NULL
  AND cs.id IS NULL
SQL,
            ['from_dt' => $fromDateTime]
        );

        $branchMismatches = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM sales s
INNER JOIN cash_sessions cs ON cs.id = s.cash_session_id
WHERE s.issued_at >= :from_dt
  AND s.branch_id <> cs.branch_id
SQL,
            ['from_dt' => $fromDateTime]
        );

        return [
            'name' => 'Integridad sesion de caja',
            'passed' => $invalidSessions === 0 && $branchMismatches === 0,
            'metric' => sprintf('invalid=%d, branch_mismatch=%d', $invalidSessions, $branchMismatches),
            'threshold' => 'invalid=0, branch_mismatch=0',
        ];
    }

    /**
     * @return array{name:string,passed:bool,metric:string,threshold:string}
     */
    private function checkPaymentIntegrity(string $fromDateTime, float $moneyTolerance): array
    {
        if (!$this->tablesExist(['sales', 'sale_payments'])) {
            return [
                'name' => 'Integridad de pagos',
                'passed' => false,
                'metric' => 'tables_missing',
                'threshold' => '0 violations',
            ];
        }

        $violations = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT
        s.id,
        ABS(s.paid_total - COALESCE(SUM(sp.amount), 0)) AS diff
    FROM sales s
    LEFT JOIN sale_payments sp ON sp.sale_id = s.id
    WHERE s.issued_at >= :from_dt
      AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    GROUP BY s.id, s.paid_total
) x
WHERE x.diff > :tol
SQL,
            [
                'from_dt' => $fromDateTime,
                'tol' => $moneyTolerance,
            ]
        );

        return [
            'name' => 'Integridad de pagos',
            'passed' => $violations === 0,
            'metric' => sprintf('%d sale(s) with diff', $violations),
            'threshold' => sprintf('0 violations (tol=%.2f)', $moneyTolerance),
        ];
    }

    /**
     * @return array{name:string,passed:bool,metric:string,threshold:string}
     */
    private function checkDashboardReadModelCoverage(
        string $fromDate,
        string $fromDateTime,
        float $moneyTolerance
    ): array {
        if (!$this->tablesExist(['sales', 'branch_daily_summary'])) {
            return [
                'name' => 'Dashboard read-model',
                'passed' => false,
                'metric' => 'tables_missing',
                'threshold' => '0 missing summaries',
            ];
        }

        $salesDays = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT DATE(issued_at) AS d, branch_id
    FROM sales
    WHERE issued_at >= :from_dt
      AND status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    GROUP BY DATE(issued_at), branch_id
) x
SQL,
            ['from_dt' => $fromDateTime]
        );

        if ($salesDays === 0) {
            return [
                'name' => 'Dashboard read-model',
                'passed' => true,
                'metric' => 'no completed sales in window',
                'threshold' => 'n/a',
            ];
        }

        $missingSummaries = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT DATE(s.issued_at) AS d, s.branch_id
    FROM sales s
    WHERE s.issued_at >= :from_dt
      AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    GROUP BY DATE(s.issued_at), s.branch_id
) sd
LEFT JOIN branch_daily_summary bds
  ON bds.summary_date = sd.d
 AND bds.branch_id = sd.branch_id
WHERE bds.branch_id IS NULL
SQL,
            ['from_dt' => $fromDateTime]
        );

        $amountDiffRows = (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT
      d.summary_date,
      d.branch_id,
      ABS(d.net_sales - r.net_sales) AS diff
    FROM branch_daily_summary d
    JOIN (
      SELECT DATE(issued_at) AS summary_date, branch_id, ROUND(SUM(grand_total),2) AS net_sales
      FROM sales
      WHERE status IN ('COMPLETED','PARTIALLY_RETURNED','RETURNED')
        AND issued_at >= :from_dt
      GROUP BY DATE(issued_at), branch_id
    ) r
      ON r.summary_date = d.summary_date
     AND r.branch_id = d.branch_id
    WHERE d.summary_date >= :from_d
) x
WHERE x.diff > :tol
SQL,
            [
                'from_dt' => $fromDateTime,
                'from_d' => $fromDate,
                'tol' => $moneyTolerance,
            ]
        );

        return [
            'name' => 'Dashboard read-model',
            'passed' => $missingSummaries === 0 && $amountDiffRows === 0,
            'metric' => sprintf('missing=%d, amount_diff_rows=%d', $missingSummaries, $amountDiffRows),
            'threshold' => sprintf('missing=0, amount_diff_rows=0 (tol=%.2f)', $moneyTolerance),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array{cnt:int,total_amount:float}>
     */
    private function toDayMap(array $rows, string $dayKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $day = (string) ($row[$dayKey] ?? '');
            if ($day === '') {
                continue;
            }

            $map[$day] = [
                'cnt' => (int) ($row['cnt'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0.0),
            ];
        }

        return $map;
    }

    /**
     * @param list<string> $tables
     */
    private function tablesExist(array $tables): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist($tables);
        } catch (Exception) {
            return false;
        }
    }
}

