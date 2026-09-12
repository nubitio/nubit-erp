<?php

declare(strict_types=1);

namespace App\Sales\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Raw-SQL reads and writes for {@see CustomerAccountBackfiller}. Kept apart so
 * the backfiller itself stays a thin orchestration + a pure timeline walk.
 */
final readonly class CustomerAccountBackfillGateway
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Non-voided credit sales with a customer, oldest first.
     *
     * @return list<array{customer_id: int, sale_id: string, occurred_on: string, amount: string, document: string}>
     *
     * @throws Exception
     */
    public function chargeRows(): array
    {
        /** @var list<array{customer_id: int, sale_id: string, occurred_on: string, amount: string, document: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                s.customer_id AS customer_id,
                s.id AS sale_id,
                COALESCE(s.issued_date, DATE(s.issued_at), DATE(s.created_at)) AS occurred_on,
                COALESCE(s.grand_total, 0.00) AS amount,
                TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))) AS document
            FROM sales s
            WHERE s.customer_id IS NOT NULL
              AND s.receivable_account = 1
              AND s.status <> 'VOIDED'
              AND s.deleted_at IS NULL
            ORDER BY occurred_on, s.issued_at, s.id
            SQL);

        return [...$rows];
    }

    /**
     * Every payment against those sales, oldest first.
     *
     * @return list<array{customer_id: int, sale_id: string, sale_payment_id: int, occurred_on: string, amount: string, document: string}>
     *
     * @throws Exception
     */
    public function paymentRows(): array
    {
        /** @var list<array{customer_id: int, sale_id: string, sale_payment_id: int, occurred_on: string, amount: string, document: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                s.customer_id AS customer_id,
                s.id AS sale_id,
                sp.id AS sale_payment_id,
                COALESCE(DATE(sp.paid_at), DATE(sp.created_at)) AS occurred_on,
                sp.amount AS amount,
                TRIM(BOTH '-' FROM CONCAT(COALESCE(s.series, ''), '-', COALESCE(s.number, ''))) AS document
            FROM sale_payments sp
            INNER JOIN sales s ON s.id = sp.sale_id
            WHERE s.customer_id IS NOT NULL
              AND s.receivable_account = 1
              AND s.status <> 'VOIDED'
              AND s.deleted_at IS NULL
            ORDER BY occurred_on, sp.id
            SQL);

        return [...$rows];
    }

    /**
     * Customer ids that already have at least one ledger entry — skipped by the
     * backfill.
     *
     * @return list<int>
     *
     * @throws Exception
     */
    public function customersWithEntries(): array
    {
        return array_map(
            static fn(mixed $id): int => (int) $id,
            $this->connection->fetchFirstColumn('SELECT DISTINCT customer_id FROM customer_account_entries'),
        );
    }

    /**
     * @param array{
     *     entry_type: string, sale_id: string, sale_payment_id: int|null, occurred_on: string,
     *     debit: string, credit: string, running_balance: string, description: string
     * } $entry
     *
     * @throws Exception
     */
    public function insertEntry(int $customerId, array $entry): void
    {
        $isCharge = $entry['entry_type'] === 'charge';

        $this->connection->insert('customer_account_entries', [
            'customer_id' => $customerId,
            'entry_type' => $entry['entry_type'],
            'occurred_on' => $entry['occurred_on'],
            'debit' => $entry['debit'],
            'credit' => $entry['credit'],
            'currency' => 'PEN',
            'running_balance' => $entry['running_balance'],
            'sale_id' => $entry['sale_id'],
            'source' => $isCharge ? 'sale' : 'sale_payment',
            'source_id' => $isCharge ? $entry['sale_id'] : (string) $entry['sale_payment_id'],
            'description' => $entry['description'] !== '' ? $entry['description'] : null,
            'created_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);

        if ($isCharge) {
            return;
        }

        $this->connection->insert('customer_payment_applications', [
            'customer_account_entry_id' => (int) $this->connection->lastInsertId(),
            'sale_id' => $entry['sale_id'],
            'sale_payment_id' => $entry['sale_payment_id'],
            'applied_amount' => $entry['credit'],
            'currency' => 'PEN',
            'applied_at' => $entry['occurred_on'] . ' 00:00:00',
        ]);
    }
}
