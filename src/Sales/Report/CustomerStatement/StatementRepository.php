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
     * @return list<array{sale_id: string, date: string, document: string, amount: string, balance_due: string}>
     *
     * @throws Exception
     */
    public function charges(int $customerId, string $dateTo): array
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
                  AND e.entry_type = 'charge'
                  AND e.occurred_on <= :date_to
                ORDER BY e.id
                SQL,
            ['customer' => $customerId, 'date_to' => $dateTo],
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
     * applied to a sale, or a credit note. Advances (`advance` /
     * `advance_applied`) are left out until #382/#383 settle how they show on
     * the statement.
     *
     * @return list<array{date: string, document: string, amount: string, detail: string}>
     *
     * @throws Exception
     */
    public function payments(int $customerId, string $dateTo): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    e.occurred_on AS date,
                    TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))) AS document,
                    e.credit AS amount,
                    CASE e.entry_type WHEN 'credit_note' THEN 'Nota de crédito' ELSE '' END AS detail
                FROM customer_account_entries e
                LEFT JOIN sales s ON s.id = e.sale_id
                WHERE e.customer_id = :customer
                  AND e.entry_type IN ('payment', 'credit_note')
                  AND e.occurred_on <= :date_to
                ORDER BY e.id
                SQL,
            ['customer' => $customerId, 'date_to' => $dateTo],
        );

        return array_map(static fn(array $r): array => [
            'date' => (string) $r['date'],
            'document' => $r['document'] === '' ? 'Documento interno' : (string) $r['document'],
            'amount' => (string) $r['amount'],
            'detail' => (string) $r['detail'],
        ], $rows);
    }
}
