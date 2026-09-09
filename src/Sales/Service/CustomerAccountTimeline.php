<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;

/**
 * Pure: merges a customer's historical charges and payments into one
 * chronological list with a running balance, for {@see CustomerAccountBackfiller}.
 * A charge sorts before a payment on the same date so a same-day pay-in-full
 * nets to zero, not negative.
 *
 * @phpstan-type ChargeRow array{customer_id: int, sale_id: string, occurred_on: string, amount: string, document: string}
 * @phpstan-type PaymentRow array{customer_id: int, sale_id: string, sale_payment_id: int, occurred_on: string, amount: string, document: string}
 * @phpstan-type LedgerRow array{
 *     entry_type: string, sale_id: string, sale_payment_id: int|null, occurred_on: string,
 *     debit: string, credit: string, running_balance: string, description: string
 * }
 */
final readonly class CustomerAccountTimeline
{
    /**
     * @param list<ChargeRow>  $charges
     * @param list<PaymentRow> $payments
     *
     * @return list<LedgerRow>
     */
    public static function merge(array $charges, array $payments): array
    {
        /** @var list<array{0: string, 1: bool, 2: array<string, mixed>}> $events */
        $events = [];
        foreach ($charges as $row) {
            $events[] = [$row['occurred_on'] . '|0', true, $row];
        }
        foreach ($payments as $row) {
            $events[] = [$row['occurred_on'] . '|1', false, $row];
        }
        usort($events, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $balance = Money::money('0.00');
        $out = [];
        foreach ($events as [$_sort, $isCharge, $row]) {
            $amount = Money::money((string) $row['amount']);
            $balance = $isCharge ? $balance->plus($amount) : $balance->minus($amount);

            $out[] = [
                'entry_type' => $isCharge ? 'charge' : 'payment',
                'sale_id' => (string) $row['sale_id'],
                'sale_payment_id' => $isCharge ? null : (int) ($row['sale_payment_id'] ?? 0),
                'occurred_on' => (string) $row['occurred_on'],
                'debit' => $isCharge ? Money::toString($amount) : '0.00',
                'credit' => $isCharge ? '0.00' : Money::toString($amount),
                'running_balance' => Money::toString($balance),
                'description' => (string) $row['document'],
            ];
        }

        return $out;
    }
}
