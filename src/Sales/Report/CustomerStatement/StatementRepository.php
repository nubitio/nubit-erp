<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Raw-SQL reads for the customer account statement (#366). Since #384 the
 * movement list is sourced from the customer ledger `customer_account_entries`
 * (populated live by the SaleConfirmed handler / the collection flow, and for
 * historical data by `app:sales:backfill-customer-account`) rather than
 * rebuilt from `sales` + `sale_payments`. `sales` is still joined for the
 * document label and the authoritative `balance_due` the aging needs.
 *
 * Sales already reaches `cliente` (Contacts) with raw SQL across its report
 * classes; this follows that pattern rather than importing a Contacts
 * repository.
 */
final readonly class StatementRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array{name: string, doc: string}|null
     *
     * @throws Exception
     */
    public function findCustomer(int $customerId): ?array
    {
        $row = $this->connection->fetchAssociative("SELECT razon_social, COALESCE(NULLIF(ruc, ''), '') AS doc FROM cliente WHERE id = :id", [
            'id' => $customerId,
        ]);

        if ($row === false) {
            return null;
        }

        return ['name' => (string) $row['razon_social'], 'doc' => (string) $row['doc']];
    }

    /**
     * Every CHARGE (a credit sale) on the customer ledger dated on or before
     * $dateTo. `balance_due` comes from the joined sale — the authoritative
     * current snapshot the aging needs.
     *
     * Charges of voided sales are left out: a voided sale must not show on the
     * statement at all. Its CHARGE_REVERSAL (if any) is never read either, as
     * {@see payments()} does not select that type, so the two stay balanced —
     * and a sale voided by an annulling credit note, which writes no reversal,
     * is covered too.
     *
     * @return list<array{sale_id: string, date: string, document: string, amount: string, balance_due: string}>
     *
     * @throws Exception
     */
    public function charges(int $customerId, string $dateTo, string $branchId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    COALESCE(e.sale_id, '') AS sale_id,
                    e.occurred_on AS date,
                    COALESCE(
                        NULLIF(e.description, ''),
                        TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, '')))
                    ) AS document,
                    e.debit AS amount,
                    COALESCE(s.balance_due, '0.00') AS balance_due
                FROM customer_account_entries e
                LEFT JOIN sales s ON s.id = e.sale_id
                WHERE e.customer_id = :customer
                  AND s.branch_id = :branch
                  AND e.entry_type = 'charge'
                  AND (s.status IS NULL OR s.status <> 'VOIDED')
                  AND e.occurred_on <= :date_to
                ORDER BY e.id
                SQL,
            ['customer' => $customerId, 'date_to' => $dateTo, 'branch' => $branchId],
        );

        return array_map(static fn(array $r): array => [
            'sale_id' => (string) $r['sale_id'],
            'date' => (string) $r['date'],
            'document' => ($r['document'] ?? '') === '' ? 'Documento interno' : (string) $r['document'],
            'amount' => (string) $r['amount'],
            'balance_due' => (string) $r['balance_due'],
        ], $rows);
    }

    /**
     * Every abono on the customer ledger dated on or before $dateTo: a payment
     * applied to a sale, a credit note, or an advance received (a credit that
     * already moved the running balance into the customer's favour). Applying an
     * advance to a sale is balance-neutral under model B (#382) — it produces no
     * entry, only a lower `balance_due` — so it never shows as a statement line.
     *
     * @return list<array{date: string, document: string, amount: string, detail: string}>
     *
     * @throws Exception
     */
    public function payments(int $customerId, string $dateTo, string $branchId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    e.occurred_on AS date,
                    CASE WHEN e.source IN ('on_account_collect', 'collection')
                        THEN 'Cobro a cuenta'
                        ELSE COALESCE(NULLIF(TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))), ''), 'Documento interno')
                    END AS document,
                    CASE WHEN e.source IN ('on_account_collect', 'collection')
                        THEN (
                            SELECT SUM(a.applied_amount)
                            FROM customer_payment_applications a
                            JOIN customer_account_entries collection_entry ON collection_entry.id = a.customer_account_entry_id
                            JOIN sales applied_sale ON applied_sale.id = a.sale_id
                            WHERE collection_entry.customer_id = e.customer_id
                              AND collection_entry.source = e.source
                              AND collection_entry.source_id = e.source_id
                              AND collection_entry.entry_type = 'payment'
                              AND applied_sale.branch_id = :collect_branch
                        )
                        ELSE e.credit
                    END AS amount,
                    CASE e.entry_type
                        WHEN 'credit_note' THEN 'Nota de crédito'
                        WHEN 'advance' THEN 'Anticipo recibido'
                        WHEN 'payment' THEN 'Cobro'
                        ELSE ''
                    END AS detail
                FROM customer_account_entries e
                LEFT JOIN sales s ON s.id = e.sale_id
                WHERE e.customer_id = :customer
                  AND (
                      (e.entry_type = 'payment' AND e.source IN ('on_account_collect', 'collection') AND EXISTS (
                          SELECT 1 FROM customer_payment_applications a
                          JOIN sales applied_sale ON applied_sale.id = a.sale_id
                          JOIN customer_account_entries collection_entry ON collection_entry.id = a.customer_account_entry_id
                          WHERE collection_entry.source = e.source AND collection_entry.source_id = e.source_id
                            AND applied_sale.branch_id = :collect_branch
                      ))
                      OR (e.entry_type = 'payment' AND (e.source IS NULL OR e.source NOT IN ('on_account_collect', 'collection')) AND s.branch_id = :branch)
                      OR (e.entry_type = 'credit_note' AND s.branch_id = :branch)
                      OR (e.entry_type = 'advance' AND e.branch_id = :advance_branch)
                  )
                  AND (
                      e.source NOT IN ('on_account_collect', 'collection')
                      OR NOT EXISTS (
                          SELECT 1 FROM customer_account_entries prior
                          WHERE prior.source = e.source AND prior.source_id = e.source_id
                            AND prior.entry_type = e.entry_type AND prior.id < e.id
                      )
                  )
                  AND e.occurred_on <= :date_to
                  AND NOT (e.source = 'collection' AND EXISTS (
                      SELECT 1 FROM customer_account_entries reversal
                      WHERE reversal.entry_type = 'payment_reversal' AND reversal.source = 'collection_reversal'
                        AND reversal.source_id = e.source_id AND reversal.occurred_on <= :date_to
                  ))
                ORDER BY e.id
                SQL,
            [
                'customer' => $customerId,
                'date_to' => $dateTo,
                'branch' => $branchId,
                'collect_branch' => $branchId,
                'advance_branch' => $branchId,
            ],
        );

        return array_map(static fn(array $r): array => [
            'date' => (string) $r['date'],
            'document' => $r['document'] === '' ? 'Documento interno' : (string) $r['document'],
            'amount' => (string) $r['amount'],
            'detail' => (string) $r['detail'],
        ], $rows);
    }

    /**
     * The customer ledger nets applications across the company. A local statement needs
     * the matching debit and credit when the received advance and the sale differ.
     *
     * @return array{
     *     charges: list<array{sale_id: string, date: string, document: string, amount: string, balance_due: string, detail: string}>,
     *     payments: list<array{date: string, document: string, amount: string, detail: string}>
     * }
     */
    public function crossBranchAdvanceApplications(int $customerId, string $dateTo, string $branchId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT e.branch_id AS origin, s.branch_id AS destination, a.applied_amount, DATE(a.applied_at) AS applied_on
                FROM customer_payment_applications a
                JOIN customer_account_entries e ON e.id = a.customer_account_entry_id
                JOIN sales s ON s.id = a.sale_id
                WHERE e.customer_id = :customer AND s.customer_id = e.customer_id
                  AND e.entry_type = 'advance'
                  AND s.branch_id IS NOT NULL
                  AND (e.branch_id IS NULL OR e.branch_id <> s.branch_id)
                  AND (e.branch_id = :origin OR s.branch_id = :destination)
                  AND DATE(a.applied_at) <= :date_to
                ORDER BY a.id
                SQL,
            [
                'customer' => $customerId,
                'origin' => $branchId,
                'destination' => $branchId,
                'date_to' => $dateTo,
            ],
        );

        $charges = [];
        $payments = [];
        foreach ($rows as $row) {
            $movement = [
                'date' => (string) $row['applied_on'],
                'amount' => (string) $row['applied_amount'],
                'document' => 'Anticipo',
            ];
            if ($row['origin'] === $branchId) {
                $charges[] = [
                    ...$movement,
                    'sale_id' => '',
                    'balance_due' => '0.00',
                    'detail' => 'Anticipo aplicado fuera de la sucursal',
                ];
            } else {
                $payments[] = [
                    ...$movement,
                    'detail' => 'Anticipo de otra sucursal aplicado',
                ];
            }
        }

        return ['charges' => $charges, 'payments' => $payments];
    }
}
