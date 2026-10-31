<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/** Read-only branch summary. Different currencies are never added together. */
final readonly class SupplierBillSummaryQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return list<array{currency: string, balanceDue: string, overdueTotal: string, dueWithinSevenDays: string, dueWithinThirtyDays: string, billCount: int, supplierCount: int}> */
    public function execute(string $branchId, DateTimeImmutable $today): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT currency,
                       SUM(balance_due) AS balance_due,
                       SUM(CASE WHEN due_date < :today THEN balance_due ELSE 0 END) AS overdue_total,
                       SUM(CASE WHEN due_date >= :today AND due_date <= :week_end THEN balance_due ELSE 0 END) AS due_seven,
                       SUM(CASE WHEN due_date >= :today AND due_date <= :month_end THEN balance_due ELSE 0 END) AS due_thirty,
                       COUNT(*) AS bill_count,
                       COUNT(DISTINCT supplier_id) AS supplier_count
                FROM supplier_bill
                WHERE branch_id = :branch_id AND balance_due > 0 AND status <> 'VOID'
                GROUP BY currency
                ORDER BY currency
                SQL,
            [
                'branch_id' => $branchId,
                'today' => $today->format('Y-m-d'),
                'week_end' => $today->modify('+7 days')->format('Y-m-d'),
                'month_end' => $today->modify('+30 days')->format('Y-m-d'),
            ],
        );

        return array_map(static fn(array $row): array => [
            'currency' => (string) $row['currency'],
            'balanceDue' => Money::toString(Money::of($row['balance_due'])),
            'overdueTotal' => Money::toString(Money::of($row['overdue_total'])),
            'dueWithinSevenDays' => Money::toString(Money::of($row['due_seven'])),
            'dueWithinThirtyDays' => Money::toString(Money::of($row['due_thirty'])),
            'billCount' => (int) $row['bill_count'],
            'supplierCount' => (int) $row['supplier_count'],
        ], $rows);
    }
}
