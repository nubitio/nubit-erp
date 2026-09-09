<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Raw-SQL reads for the customer account statement. Sales already reaches
 * `cliente` (Contacts) with raw SQL across its report classes; this follows
 * that pattern rather than importing a Contacts repository.
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
     * Every non-voided credit sale for the customer issued on or before $dateTo.
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
                    s.id AS sale_id,
                    s.issued_date AS date,
                    TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))) AS document,
                    s.grand_total AS amount,
                    s.balance_due AS balance_due
                FROM sales s
                WHERE s.customer_id = :customer
                  AND s.receivable_account = 1
                  AND s.status <> 'VOIDED'
                  AND s.deleted_at IS NULL
                  AND s.issued_date <= :date_to
                ORDER BY s.issued_date, s.issued_at
                SQL,
            ['customer' => $customerId, 'date_to' => $dateTo],
        );

        return array_map(static fn(array $r): array => [
            'sale_id' => (string) $r['sale_id'],
            'date' => (string) $r['date'],
            'document' => $r['document'] === '' ? 'Documento interno' : (string) $r['document'],
            'amount' => (string) $r['amount'],
            'balance_due' => (string) $r['balance_due'],
        ], $rows);
    }

    /**
     * Every abono against those credit sales paid on or before $dateTo.
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
                    DATE(sp.paid_at) AS date,
                    TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))) AS document,
                    sp.amount AS amount,
                    COALESCE(sp.reference, '') AS detail
                FROM sale_payments sp
                INNER JOIN sales s ON s.id = sp.sale_id
                WHERE s.customer_id = :customer
                  AND s.receivable_account = 1
                  AND s.status <> 'VOIDED'
                  AND s.deleted_at IS NULL
                  AND DATE(sp.paid_at) <= :date_to
                ORDER BY sp.paid_at
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
