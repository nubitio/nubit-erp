<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Read-only 30-day cash outlook for a branch, in PEN only.
 *
 * Bank balances are intentionally not included: bank statement imports store
 * transactions but no running balance, so there is no trustworthy figure.
 */
final readonly class SupplierBillForecastQuery
{
    public function __construct(
        private Connection $connection,
        private SupplierBillSummaryQuery $summary,
    ) {}

    /** @return array{currency: string, cashBalance: string, expectedReceipts: string, payables: string, projectedBalance: string, overdueReceivables: string, overdueReceivablesCount: int} */
    public function execute(string $branchId, DateTimeImmutable $today): array
    {
        $cash = Money::of(
            $this->connection->fetchOne(
                <<<'SQL'
                    SELECT COALESCE(SUM(s.opening_amount
                        + COALESCE(m.sale_in, 0) + COALESCE(m.cash_in, 0)
                        - COALESCE(m.cash_out, 0) - COALESCE(m.refund_out, 0)), 0)
                    FROM cash_sessions s
                    LEFT JOIN (
                        SELECT cash_session_id,
                               SUM(CASE WHEN movement_type = 'SALE_IN' THEN amount ELSE 0 END) AS sale_in,
                               SUM(CASE WHEN movement_type = 'CASH_IN' THEN amount ELSE 0 END) AS cash_in,
                               SUM(CASE WHEN movement_type = 'CASH_OUT' THEN amount ELSE 0 END) AS cash_out,
                               SUM(CASE WHEN movement_type = 'REFUND_OUT' THEN amount ELSE 0 END) AS refund_out
                        FROM cash_movements
                        GROUP BY cash_session_id
                    ) m ON m.cash_session_id = s.id
                    WHERE s.branch_id = :branch_id AND s.status = 'OPEN'
                    SQL,
                ['branch_id' => $branchId],
            ) ?: '0',
        );

        $receivables = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT COALESCE(SUM(CASE WHEN due_date >= :today AND due_date <= :month_end THEN balance_due ELSE 0 END), 0) AS expected,
                       COALESCE(SUM(CASE WHEN due_date < :today THEN balance_due ELSE 0 END), 0) AS overdue,
                       COALESCE(SUM(CASE WHEN due_date < :today THEN 1 ELSE 0 END), 0) AS overdue_count
                FROM sales
                WHERE branch_id = :branch_id AND currency = 'PEN' AND balance_due > 0
                  AND status <> 'VOIDED' AND due_date IS NOT NULL
                SQL,
            [
                'branch_id' => $branchId,
                'today' => $today->format('Y-m-d'),
                'month_end' => $today->modify('+30 days')->format('Y-m-d'),
            ],
        ) ?: ['expected' => '0', 'overdue' => '0', 'overdue_count' => 0];

        $payables = '0.00';
        foreach ($this->summary->execute($branchId, $today) as $row) {
            if ($row['currency'] === 'PEN') {
                $payables = $row['dueWithinThirtyDays'];
            }
        }

        $expected = Money::of($receivables['expected']);
        $projected = Money::sub(Money::add($cash, $expected), Money::of($payables));

        return [
            'currency' => 'PEN',
            'cashBalance' => Money::toString($cash),
            'expectedReceipts' => Money::toString($expected),
            'payables' => $payables,
            'projectedBalance' => Money::toString($projected),
            'overdueReceivables' => Money::toString(Money::of($receivables['overdue'])),
            'overdueReceivablesCount' => (int) $receivables['overdue_count'],
        ];
    }
}
