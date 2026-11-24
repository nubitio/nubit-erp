<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/** What the drawer of a shift holds and what left it by hand, as its reports print them. */
readonly class ShiftCash
{
    private const array WITHDRAWAL_CATEGORIES = ['manager_withdrawal', 'bank_deposit'];

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * What the drawer holds, built the same way as the X report endpoint: opening, cash sales, income, expenses and
     * withdrawals, giving the expected cash; plus what was counted and the difference once closed.
     *
     * @param array<string, mixed> $session
     * @return array{opening: string, cashSales: string, income: string, expenses: string, withdrawals: string, expected: string, counted: ?string, difference: ?string}
     * @throws Exception
     */
    public function summary(int $sessionId, array $session): array
    {
        $opening = BigDecimal::of((string) $session['opening_amount']);
        $sum = fn(string $where): BigDecimal => BigDecimal::of(
            (string) ($this->connection->fetchOne("SELECT COALESCE(SUM(cm.amount), 0) FROM cash_movements cm
             LEFT JOIN cash_movement_category cmc ON cmc.id = cm.category_id
             WHERE cm.cash_session_id = ? AND {$where}", [$sessionId]) ?? '0'),
        );

        $cashSales = $sum("cm.movement_type = 'SALE_IN'");
        $income = $sum("cm.movement_type = 'CASH_IN'");
        $out = $sum("cm.movement_type IN ('CASH_OUT', 'REFUND_OUT')");
        $withdrawals = $sum("cm.movement_type = 'CASH_OUT' AND cmc.code IN ('manager_withdrawal', 'bank_deposit')");
        $expected = $opening->plus($cashSales)->plus($income)->minus($out);

        return [
            'opening' => $this->money($opening),
            'cashSales' => $this->money($cashSales),
            'income' => $this->money($income),
            'expenses' => $this->money($out->minus($withdrawals)),
            'withdrawals' => $this->money($withdrawals),
            'expected' => $this->money($session['expected_amount'] ?? $expected),
            'counted' => $session['counted_amount'] === null ? null : $this->money($session['counted_amount']),
            'difference' => $session['difference_amount'] === null ? null : $this->money($session['difference_amount']),
        ];
    }

    /**
     * The cash that left the drawer by hand (expenses and withdrawals), oldest first.
     *
     * @return list<array{at: string, kind: string, description: string, amount: string}>
     * @throws Exception
     */
    public function outflows(int $sessionId): array
    {
        $rows = $this->connection->fetchAllAssociative("SELECT cm.id, cm.created_at, cm.amount, cm.description, cmc.code, cmc.name
             FROM cash_movements cm
             LEFT JOIN cash_movement_category cmc ON cmc.id = cm.category_id
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'CASH_OUT'
             ORDER BY cm.created_at, cm.id", [$sessionId]);

        return array_map(fn(array $row): array => [
            'at' => $this->iso($row['created_at']) ?? '',
            'kind' => in_array($row['code'], self::WITHDRAWAL_CATEGORIES, strict: true) ? 'withdrawal' : 'expense',
            'description' => (string) (
                ($row['description'] ?? '') !== '' ? $row['description'] : $row['name'] ?? 'Egreso'
            ),
            'amount' => $this->money($row['amount']),
        ], $rows);
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : new \DateTimeImmutable((string) $value)->format(DATE_ATOM);
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
