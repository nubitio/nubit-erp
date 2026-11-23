<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;

/**
 * What a shift sold, by the way it was paid. The reports of the shift (X cut, closing form, the cashier's list of
 * sales) all read it from here, so they cannot show three different numbers for the same shift.
 *
 * It comes from the payments of the sales themselves, not from the cash drawer: only cash moves the drawer
 * (Yape, Plin or a card go to the treasury), so the drawer alone knows nothing of them. What was left on credit
 * is not money yet and is reported apart.
 *
 * Voided sales are left out. Only the payments made when the sale was rung up count: a later collection of a
 * credit belongs to the shift that collects it (those rows carry no idempotency key, the sale's own do).
 */
readonly class SessionSalesBreakdown
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array{
     *     methods: list<array{id: int, name: string, amount: string, operations: int}>,
     *     receivable: array{amount: string, operations: int},
     *     total: string
     * }
     */
    public function forSession(int $sessionId): array
    {
        $rows = $this->connection->fetchAllAssociative("SELECT pm.id, pm.name, COALESCE(SUM(sp.amount), 0) AS amount, COUNT(DISTINCT sp.sale_id) AS operations
             FROM sale_payments sp
             JOIN sales s ON s.id = sp.sale_id
             JOIN payment_method pm ON pm.id = sp.payment_method_id
             WHERE s.cash_session_id = ? AND s.deleted_at IS NULL AND s.status <> 'VOIDED'
               AND sp.idempotency_key IS NOT NULL
             GROUP BY pm.id, pm.name
             ORDER BY pm.id", [$sessionId]);

        $methods = [];
        foreach ($rows as $row) {
            $methods[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'amount' => $this->money($row['amount']),
                'operations' => (int) $row['operations'],
            ];
        }

        $credit = $this->connection->fetchAssociative(
            "SELECT COALESCE(SUM(s.grand_total - COALESCE(p.paid, 0)), 0) AS amount, COUNT(*) AS operations
             FROM sales s
             LEFT JOIN (
                 SELECT sale_id, SUM(amount) AS paid FROM sale_payments WHERE idempotency_key IS NOT NULL GROUP BY sale_id
             ) p ON p.sale_id = s.id
             WHERE s.cash_session_id = ? AND s.deleted_at IS NULL AND s.status <> 'VOIDED'
               AND s.receivable_account = 1 AND s.grand_total > COALESCE(p.paid, 0)",
            [$sessionId],
        );

        $total = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(grand_total), 0) FROM sales
             WHERE cash_session_id = ? AND deleted_at IS NULL AND status <> 'VOIDED'",
            [$sessionId],
        );

        return [
            'methods' => $methods,
            'receivable' => [
                'amount' => $this->money($credit['amount'] ?? 0),
                'operations' => (int) ($credit['operations'] ?? 0),
            ],
            'total' => $this->money($total),
        ];
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value === false || $value === null ? '0' : $value))->toScale(
            2,
            RoundingMode::HALF_UP,
        )->__toString();
    }
}
