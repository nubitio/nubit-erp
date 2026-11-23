<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;

/**
 * What happens around the till besides selling: credit notes, cash taken out, and how each count comes out.
 * The days are fixed so the story reads the same every night.
 */
final class ShiftEvents
{
    /** Days ago on which Luis's till came out short, and by how much (S/). */
    private const array LUIS_SHORTAGES = [1 => 9.5, 3 => 14.0, 4 => 22.5, 8 => 11.0, 11 => 28.0, 14 => 9.0, 19 => 17.5];

    /** Days ago on which Luis took cash out of the till with a vague reason, and how much. */
    private const array LUIS_OUTFLOWS = [2 => 35.0, 6 => 20.0, 9 => 45.0, 13 => 30.0];

    /** Days ago on which Luis issued a credit note. */
    private const array LUIS_CREDIT_NOTES = [17, 9, 3];

    /** The single credit note María issued, so the comparison is not "one has them, one has none". */
    private const int MARIA_CREDIT_NOTE_DAY = 10;

    private const int MARIA_PETTY_CASH_DAY = 5;

    /** A returned sale is at least this much (S/), prices included. */
    private const float MIN_CREDIT_NOTE_AMOUNT = 20.0;

    public function __construct(
        private readonly DemoRandom $random,
    ) {}

    public function hasCreditNote(string $cashier, int $daysAgo): bool
    {
        return match ($cashier) {
            'luis' => in_array($daysAgo, self::LUIS_CREDIT_NOTES, true),
            'maria' => $daysAgo === self::MARIA_CREDIT_NOTE_DAY,
            default => false,
        };
    }

    /**
     * Turns a plain, walk-in sale from the middle of the turn into a fully returned one.
     *
     * @param list<SalePlan> $sales
     *
     * @return list<SalePlan>
     */
    public function applyCreditNote(array $sales): array
    {
        $candidates = array_keys(array_filter(
            $sales,
            static fn(SalePlan $s): bool => (
                $s->customerKey === null
                && $s->outcome === SalePlan::NORMAL
                && $s->discountPercent === 0.0
            ),
        ));
        if ($candidates === []) {
            return $sales;
        }

        // The one nearest the middle of the turn that is worth returning: nobody issues a credit note for a bread roll.
        $worth = array_values(array_filter(
            $candidates,
            fn(int $i): bool => self::gross($sales[$i]) >= self::MIN_CREDIT_NOTE_AMOUNT,
        ));
        $pool = $worth !== [] ? $worth : $candidates;
        $index = $pool[intdiv(count($pool), 2)];
        $sale = $sales[$index];
        $sales[$index] = new SalePlan(
            cashier: $sale->cashier,
            at: $sale->at,
            customerKey: $sale->customerKey,
            lines: $sale->lines,
            payment: $sale->payment,
            outcome: SalePlan::CREDIT_NOTE,
            outcomeReason: 'Devolución total del cliente',
            outcomeAt: $sale->at->modify('+40 minutes'),
        );

        return array_values($sales);
    }

    /**
     * The very last sale of today has not been confirmed by SUNAT yet: the invoicing screen shows a
     * pending state next to all the accepted ones.
     *
     * @param list<SalePlan> $sales
     *
     * @return list<SalePlan>
     */
    public function markLastPending(array $sales): array
    {
        if ($sales === []) {
            return $sales;
        }

        $last = array_key_last($sales);
        $sale = $sales[$last];
        $sales[$last] = new SalePlan(
            cashier: $sale->cashier,
            at: $sale->at,
            customerKey: $sale->customerKey,
            lines: $sale->lines,
            payment: $sale->payment,
            discountPercent: $sale->discountPercent,
            discountReason: $sale->discountReason,
            outcome: $sale->outcome,
            outcomeReason: $sale->outcomeReason,
            outcomeAt: $sale->outcomeAt,
            sunat: 'pending',
        );

        return $sales;
    }

    /** @return list<array{at: DateTimeImmutable, amount: float, category: string, description: string}> */
    public function outflows(string $cashier, DateTimeImmutable $date, int $daysAgo): array
    {
        if ($cashier === 'luis' && isset(self::LUIS_OUTFLOWS[$daysAgo])) {
            return [[
                'at' => $date->setTime(17, 30 + ($daysAgo % 20)),
                'amount' => (float) self::LUIS_OUTFLOWS[$daysAgo],
                'category' => 'petty_cash_expense',
                'description' => (string) $this->random->pick(['Varios', 'Gasto', 'Compra urgente', 'Movilidad']),
            ]];
        }
        if ($cashier === 'maria' && $daysAgo === self::MARIA_PETTY_CASH_DAY) {
            return [[
                'at' => $date->setTime(10, 20),
                'amount' => 12.0,
                'category' => 'petty_cash_expense',
                'description' => 'Bolsas y cinta de embalaje (boleta adjunta)',
            ]];
        }

        return [];
    }

    /** Counted minus expected at closing. María rounds coins now and then; Luis comes up short on fixed days. */
    public function difference(string $cashier, int $daysAgo): float
    {
        if ($cashier === 'luis' && isset(self::LUIS_SHORTAGES[$daysAgo])) {
            return -(float) self::LUIS_SHORTAGES[$daysAgo];
        }

        return (float) $this->random->pick(
            $cashier === 'luis' ? [0.0, 0.0, 0.2, -0.3] : [0.0, 0.0, 0.0, 0.3, -0.2, 0.5],
        );
    }

    public function closeNote(string $cashier, int $daysAgo): ?string
    {
        return $cashier === 'luis' && isset(self::LUIS_SHORTAGES[$daysAgo]) ? 'Arqueo con diferencia' : null;
    }

    private static function gross(SalePlan $sale): float
    {
        $prices = array_column(MinimarketCatalog::products(), 'price', 'code');

        return array_sum(array_map(
            static fn(array $line): float => $line['qty'] * (float) $prices[$line['code']],
            $sale->lines,
        ));
    }
}
