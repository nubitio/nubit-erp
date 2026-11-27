<?php

declare(strict_types=1);

namespace App\AccountsPayable\Audit;

use Doctrine\DBAL\Connection;

/** Read-only consistency rules over supplier bills and payments (finance refactoring, T0.7). */
final readonly class PayablesAudit
{
    private const int SAMPLE = 20;

    /** @var array<string, array{description: string, sql: string}> */
    private const array RULES = [
        'AP-01' => [
            'description' => 'supplier_bill: amount minus non-reversed payments must equal balance_due',
            'sql' => "SELECT b.id, b.amount, b.paid_total, b.balance_due,
                             COALESCE(SUM(CASE WHEN p.reversed_at IS NULL THEN p.amount END), 0) AS payments
                      FROM supplier_bill b LEFT JOIN supplier_payment p ON p.supplier_bill_id = b.id
                      WHERE b.status <> 'VOID'
                      GROUP BY b.id, b.amount, b.paid_total, b.balance_due
                      HAVING b.amount - COALESCE(SUM(CASE WHEN p.reversed_at IS NULL THEN p.amount END), 0) <> b.balance_due
                          OR b.paid_total <> COALESCE(SUM(CASE WHEN p.reversed_at IS NULL THEN p.amount END), 0)",
        ],
        'AP-02' => [
            'description' => 'supplier_bill: negative balance_due or paid_total',
            'sql' => 'SELECT id, paid_total, balance_due FROM supplier_bill WHERE balance_due < 0 OR paid_total < 0',
        ],
        'AP-03' => [
            'description' => 'supplier_bill: status does not match the balance',
            'sql' => "SELECT id, status, amount, paid_total, balance_due FROM supplier_bill
                      WHERE status <> 'VOID' AND (
                            (balance_due = 0 AND status <> 'PAID')
                         OR (balance_due > 0 AND paid_total > 0 AND status <> 'PARTIAL')
                         OR (balance_due > 0 AND paid_total = 0 AND status <> 'PENDING'))",
        ],
        'AP-04' => [
            'description' => 'supplier_bill: more than one bill linked to the same purchase',
            'sql' => "SELECT purchase_id, COUNT(*) AS bills FROM supplier_bill
                      WHERE purchase_id IS NOT NULL AND status <> 'VOID'
                      GROUP BY purchase_id HAVING COUNT(*) > 1",
        ],
    ];

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array<string, array{description: string, count: int, sample: list<array<string, mixed>>}>
     */
    public function run(): array
    {
        $report = [];
        foreach (self::RULES as $id => $rule) {
            $rows = $this->connection->fetchAllAssociative($rule['sql'] . ' LIMIT 100000');
            $report[$id] = [
                'description' => $rule['description'],
                'count' => count($rows),
                'sample' => array_slice($rows, 0, self::SAMPLE),
            ];
        }

        return $report;
    }
}
