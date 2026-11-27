<?php

declare(strict_types=1);

namespace App\Sales\Audit;

use Doctrine\DBAL\Connection;

/**
 * Read-only consistency rules over the receivables tables (finance refactoring, T0.7). Each rule is a
 * query returning the offending rows; an empty result means the rule holds. Nothing is repaired here:
 * the rows that cannot be explained deterministically go to a manual reconciliation (see
 * docs/architecture/financial-refactoring/05-data-migration.md, section 3).
 */
final readonly class ReceivablesAudit
{
    private const int SAMPLE = 20;

    /** @var array<string, array{description: string, sql: string}> */
    private const array RULES = [
        'AR-01' => [
            'description' => 'sales: paid_total + balance_due + credit notes applied must equal grand_total (non-voided)',
            'sql' => "SELECT s.id, s.grand_total, s.paid_total, s.balance_due FROM sales s
                      WHERE s.status <> 'VOIDED' AND s.deleted_at IS NULL
                        AND s.paid_total + s.balance_due + COALESCE((
                            SELECT SUM(e.credit) FROM customer_account_entries e
                            WHERE e.sale_id = s.id AND e.entry_type = 'credit_note'), 0) <> s.grand_total",
        ],
        'AR-02' => [
            'description' => 'sales: balance_due or paid_total is negative',
            'sql' => "SELECT id, paid_total, balance_due FROM sales
                      WHERE balance_due < 0 OR paid_total < 0",
        ],
        'AR-03' => [
            'description' => 'sales: paid_total differs from the sum of its sale_payments (non-voided)',
            'sql' => "SELECT s.id, s.paid_total, COALESCE(SUM(p.amount), 0) AS payments
                      FROM sales s LEFT JOIN sale_payments p ON p.sale_id = s.id
                      WHERE s.status <> 'VOIDED' AND s.deleted_at IS NULL
                      GROUP BY s.id, s.paid_total
                      HAVING s.paid_total <> COALESCE(SUM(p.amount), 0)",
        ],
        'AR-04' => [
            'description' => 'customer ledger: duplicated entry for the same (source, source_id, entry_type)',
            'sql' => "SELECT source, source_id, entry_type, COUNT(*) AS entries
                      FROM customer_account_entries
                      WHERE source IS NOT NULL AND source_id IS NOT NULL
                      GROUP BY source, source_id, entry_type
                      HAVING COUNT(*) > 1",
        ],
        'AR-05' => [
            'description' => 'customer ledger: last running_balance differs from the sum of debits minus credits',
            'sql' => "SELECT e.customer_id, SUM(e.debit) - SUM(e.credit) AS expected,
                             (SELECT l.running_balance FROM customer_account_entries l
                              WHERE l.customer_id = e.customer_id ORDER BY l.id DESC LIMIT 1) AS stored
                      FROM customer_account_entries e
                      GROUP BY e.customer_id
                      HAVING SUM(e.debit) - SUM(e.credit) <> (
                          SELECT l.running_balance FROM customer_account_entries l
                          WHERE l.customer_id = e.customer_id ORDER BY l.id DESC LIMIT 1)",
        ],
        'AR-06' => [
            'description' => 'applications: sum applied for a sale_payment differs from the payment amount',
            'sql' => "SELECT a.sale_payment_id, p.amount, SUM(a.applied_amount) AS applied
                      FROM customer_payment_applications a
                      JOIN sale_payments p ON p.id = a.sale_payment_id
                      GROUP BY a.sale_payment_id, p.amount
                      HAVING SUM(a.applied_amount) <> p.amount",
        ],
        'AR-07' => [
            'description' => 'applications: currency differs from the currency of the sale',
            'sql' => "SELECT a.id, a.sale_id, a.currency AS applied_currency, s.currency AS sale_currency
                      FROM customer_payment_applications a
                      JOIN sales s ON s.id = a.sale_id
                      WHERE a.currency <> s.currency",
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
                'sample' => array_slice($rows, offset: 0, length: self::SAMPLE),
            ];
        }

        return $report;
    }
}
