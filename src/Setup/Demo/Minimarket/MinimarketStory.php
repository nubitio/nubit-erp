<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The plot of the demo: forty-five days of a neighbourhood minimarket with two cashiers.
 *
 * - María (mornings) is the reference: a handful of small discounts, almost no voids, a till that
 *   closes within coins of what it should.
 * - Luis (afternoons) changes over the last three weeks: he voids far more than the others, gives
 *   steeper and more frequent discounts, closes short again and again and takes small cash out with
 *   vague reasons. Nothing says "thief": it is the pattern the owner would want to look at.
 * - Carlos is behind on his tab, Rosa and the restaurant are up to date.
 * - Today only María has worked, and her turn is still open so the till is ready to sell.
 *
 * Pure and deterministic: the same `$now` always gives the same story, so every nightly reset
 * looks identical. It knows nothing about the database.
 */
final class MinimarketStory
{
    public const int HISTORY_DAYS = 45;
    /** Luis's drift starts this many days before today. */
    public const int PATTERN_DAYS = 21;

    private const int SEED = 20_261_122;

    /** Today's morning sells this many times what the whole of yesterday did. */
    private const float BUSY_MORNING = 1.1;

    private function __construct(
        private readonly DemoRandom $random,
        private readonly BasketFactory $baskets,
        private readonly AccountSales $accountSales,
        private readonly ShiftEvents $events,
    ) {}

    /** @return list<ShiftPlan> oldest first */
    public static function build(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable(
            'now',
            new DateTimeZone(MinimarketCatalog::TIMEZONE),
        ))->setTimezone(new DateTimeZone(MinimarketCatalog::TIMEZONE));

        $random = new DemoRandom(self::SEED);

        return new self(
            $random,
            new BasketFactory($random),
            new AccountSales(),
            new ShiftEvents($random),
        )->plan($now->setTime(0, 0));
    }

    /** @return list<ShiftPlan> */
    private function plan(DateTimeImmutable $today): array
    {
        $shifts = [];
        $yesterdaySales = 0;
        for ($daysAgo = self::HISTORY_DAYS - 1; $daysAgo >= 0; --$daysAgo) {
            $date = $today->modify(sprintf('-%d days', $daysAgo));
            $daySales = 0;
            foreach (array_keys(MinimarketCatalog::cashiers()) as $cashier) {
                if ($daysAgo === 0 && $cashier === 'luis') {
                    continue; // Luis starts after lunch; the demo is always reset before then.
                }
                // Today only the morning has happened, yet it should not read as a bad day next to a whole
                // yesterday: a busy morning sells a bit more than the whole day before did.
                $floor = $daysAgo === 0 ? (int) round($yesterdaySales * self::BUSY_MORNING) : 0;
                $shift = $this->shift($cashier, $date, $daysAgo, $floor);
                $daySales += count($shift->sales);
                $shifts[] = $shift;
            }
            $yesterdaySales = $daySales;
        }

        return $shifts;
    }

    private function shift(string $cashier, DateTimeImmutable $date, int $daysAgo, int $minSales): ShiftPlan
    {
        $conf = MinimarketCatalog::cashiers()[$cashier];
        $start = $date->setTime($conf['shiftStart'], 0);
        $end = $date->setTime($conf['shiftEnd'], 0);
        $drifting = $cashier === 'luis' && $daysAgo >= 1 && $daysAgo <= self::PATTERN_DAYS;

        $sales = [];
        foreach ($this->sellTimes($start, $end, max($minSales, $this->salesToday($cashier, $date, $daysAgo))) as $at) {
            $sales[] = $this->baskets->walkIn($cashier, $at, $drifting);
        }
        if ($cashier === 'maria') {
            array_push($sales, ...$this->accountSales->forDay($daysAgo, $start));
        }
        usort($sales, static fn(SalePlan $a, SalePlan $b): int => $a->at <=> $b->at);

        if ($this->events->hasCreditNote($cashier, $daysAgo)) {
            $sales = $this->events->applyCreditNote($sales);
        }
        if ($daysAgo === 0 && $cashier === 'maria') {
            $sales = $this->events->markLastPending($sales);
        }

        return new ShiftPlan(
            cashier: $cashier,
            opensAt: $start->modify(sprintf('-%d minutes', $this->random->between(2, 12))),
            closesAt: $daysAgo === 0 ? null : $end->modify(sprintf('+%d minutes', $this->random->between(4, 25))),
            openingAmount: 150.0,
            sales: $sales,
            outflows: $this->events->outflows($cashier, $date, $daysAgo),
            difference: $daysAgo === 0 ? null : $this->events->difference($cashier, $daysAgo),
            closeNote: $this->events->closeNote($cashier, $daysAgo),
        );
    }

    private function salesToday(string $cashier, DateTimeImmutable $date, int $daysAgo): int
    {
        $base = $cashier === 'maria' ? 20 : 24;
        $factor = match ((int) $date->format('N')) {
            5, 6 => 1.4,
            7 => 1.1,
            default => 1.0,
        };

        return (int) round($base * $factor * (0.85 + (0.3 * $this->random->next())));
    }

    /** @return list<DateTimeImmutable> */
    private function sellTimes(DateTimeImmutable $start, DateTimeImmutable $end, int $count): array
    {
        $span = $end->getTimestamp() - $start->getTimestamp() - (30 * 60);
        $times = [];
        for ($i = 0; $i < $count; ++$i) {
            // The average of two rolls bunches sales around the middle of the turn.
            $offset = (int) ((($this->random->next() + $this->random->next()) / 2) * $span);
            $times[] = $start->modify(sprintf('+%d seconds', (10 * 60) + $offset));
        }
        sort($times);

        return $times;
    }
}
