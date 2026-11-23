<?php

declare(strict_types=1);

namespace App\Tests\Setup\Demo\Minimarket;

use App\Reporting\TeamControl\TeamControlQuery;
use App\Reporting\TeamControl\TeamControlRules;
use App\Setup\Demo\Minimarket\MinimarketCatalog;
use App\Setup\Demo\Minimarket\MinimarketStory;
use App\Setup\Demo\Minimarket\SalePlan;
use App\Setup\Demo\Minimarket\ShiftPlan;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class MinimarketStoryTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-22 03:00:00', new DateTimeZone(MinimarketCatalog::TIMEZONE));
    }

    /** @return list<ShiftPlan> */
    private function shifts(): array
    {
        return MinimarketStory::build($this->now());
    }

    public function testTheStoryIsTheSameEveryNight(): void
    {
        self::assertEquals($this->shifts(), $this->shifts());
    }

    public function testOnlyMariaHasWorkedTodayAndHerTillIsOpen(): void
    {
        $today = array_values(array_filter(
            $this->shifts(),
            static fn(ShiftPlan $s): bool => $s->opensAt->format('Y-m-d') === '2026-11-22',
        ));

        self::assertCount(1, $today);
        self::assertSame('maria', $today[0]->cashier);
        self::assertTrue($today[0]->isOpen());
        self::assertNotEmpty($today[0]->sales);
        self::assertSame(1, count(array_filter($this->shifts(), static fn(ShiftPlan $s): bool => $s->isOpen())));
    }

    public function testSalesStayInsideTheirShiftAndNeverInTheFuture(): void
    {
        foreach ($this->shifts() as $shift) {
            foreach ($shift->sales as $sale) {
                self::assertGreaterThanOrEqual($shift->opensAt, $sale->at);
                if (!$shift->isOpen()) {
                    self::assertLessThanOrEqual($shift->closesAt, $sale->at);
                }
            }
        }
    }

    public function testProductsThatDoNotMoveAreNeverSold(): void
    {
        $dead = array_column(
            array_filter(MinimarketCatalog::products(), static fn(array $p): bool => $p['weight'] === 0),
            'code',
        );
        self::assertNotEmpty($dead);

        $sold = [];
        foreach ($this->shifts() as $shift) {
            foreach ($shift->sales as $sale) {
                foreach ($sale->lines as $line) {
                    $sold[$line['code']] = ($sold[$line['code']] ?? 0) + $line['qty'];
                }
            }
        }

        self::assertSame([], array_values(array_intersect($dead, array_keys($sold))));
        self::assertArrayHasKey('INK15', $sold);
    }

    public function testEveryLineReferencesARealProduct(): void
    {
        $codes = array_column(MinimarketCatalog::products(), 'code');
        foreach ($this->shifts() as $shift) {
            foreach ($shift->sales as $sale) {
                foreach ($sale->lines as $line) {
                    self::assertContains($line['code'], $codes);
                    self::assertGreaterThan(0, $line['qty']);
                }
            }
        }
    }

    public function testCarlosOwesAndIsOverdueWhileRosaAndTheRestaurantAreNot(): void
    {
        $credit = [];
        foreach ($this->shifts() as $shift) {
            foreach ($shift->sales as $sale) {
                if ($sale->isCredit()) {
                    $credit[(string) $sale->customerKey][] = $sale->at->modify(sprintf('+%d days', $sale->creditDays));
                }
            }
        }

        $today = $this->now()->setTime(0, 0);
        self::assertCount(4, $credit['carlos']);
        foreach ($credit['carlos'] as $due) {
            self::assertLessThan($today, $due, 'every one of Carlos\'s tabs is past due');
        }
        foreach ([...$credit['rosa'], ...$credit['restaurante']] as $due) {
            self::assertGreaterThanOrEqual($today, $due, 'the other tabs are still current');
        }
    }

    public function testNoOneBuysOnCreditWithoutBeingAKnownCustomer(): void
    {
        foreach ($this->shifts() as $shift) {
            foreach ($shift->sales as $sale) {
                if ($sale->isCredit()) {
                    self::assertNotNull($sale->customerKey);
                }
            }
        }
    }

    public function testOneFacturaIsRejectedAndTodaysLastSaleIsPending(): void
    {
        $states = array_count_values(array_map(
            static fn(SalePlan $s): string => $s->sunat,
            array_merge(...array_map(static fn(ShiftPlan $s): array => $s->sales, $this->shifts())),
        ));

        self::assertSame(1, $states['rejected']);
        self::assertSame(1, $states['pending']);
    }
}
