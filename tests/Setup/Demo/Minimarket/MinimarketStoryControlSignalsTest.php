<?php

declare(strict_types=1);

namespace App\Tests\Setup\Demo\Minimarket;

use App\Reporting\TeamControl\TeamControlQuery;
use App\Reporting\TeamControl\TeamControlRules;
use App\Setup\Demo\Minimarket\MinimarketCatalog;
use App\Setup\Demo\Minimarket\MinimarketStory;
use App\Setup\Demo\Minimarket\SalePlan;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** The point of the demo: read through "Control de caja y equipo", María is in order and Luis is worth a look. */
final class MinimarketStoryControlSignalsTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-22 03:00:00', new DateTimeZone(MinimarketCatalog::TIMEZONE));
    }

    public function testTheTeamControlViewFlagsLuisAndLeavesMariaAlone(): void
    {
        foreach ([7, 21, 30] as $days) {
            $assessed = $this->assess($days);

            self::assertSame('ok', $assessed['maria']['level'], "María over $days days");
            self::assertSame('review', $assessed['luis']['level'], "Luis over $days days");
        }

        $keys = array_column($this->assess(21)['luis']['signals'], 'key');
        self::assertContains('voids', $keys);
        self::assertContains('discounts', $keys);
        self::assertContains('cash_shortage', $keys);
        self::assertSame([], $this->assess(21)['maria']['signals']);
    }

    public function testLuisWasFineBeforeTheDriftBegan(): void
    {
        // The 21 days before the pattern starts: the same people, nothing to flag yet.
        $assessed = $this->assess(21, offsetDays: MinimarketStory::PATTERN_DAYS);

        self::assertSame('ok', $assessed['luis']['level']);
        self::assertSame('ok', $assessed['maria']['level']);
    }

    /**
     * The same figures the Team Control query reads from the database, computed from the plan.
     *
     * @return array<string, array{level: string, signals: list<array{key: string, label: string, detail: string}>}>
     */
    private function assess(int $days, int $offsetDays = 0): array
    {
        $today = $this->now()->setTime(0, 0);
        $to = $today->modify(sprintf('-%d days', $offsetDays))->modify('+1 day');
        $from = $to->modify(sprintf('-%d days', $days));
        $prices = array_column(MinimarketCatalog::products(), 'price', 'code');

        $blank = [
            'salesCount' => 0,
            'salesTotal' => 0.0,
            'subtotal' => 0.0,
            'discountTotal' => 0.0,
            'voidCount' => 0,
            'voidTotal' => 0.0,
            'creditNoteCount' => 0,
            'creditNoteTotal' => 0.0,
            'cashSessions' => 0,
            'cashShortCount' => 0,
            'cashShortage' => 0.0,
            'cashSurplus' => 0.0,
            'manualOutflowCount' => 0,
            'manualOutflowTotal' => 0.0,
        ];
        $metrics = ['maria' => $blank, 'luis' => $blank];

        foreach (MinimarketStory::build($this->now()) as $shift) {
            $m = &$metrics[$shift->cashier];
            if ($shift->closesAt !== null && $shift->closesAt >= $from && $shift->closesAt < $to) {
                ++$m['cashSessions'];
                if ($shift->difference !== null && $shift->difference <= -TeamControlQuery::CASH_TOLERANCE) {
                    ++$m['cashShortCount'];
                    $m['cashShortage'] += $shift->difference;
                }
            }
            foreach ($shift->sales as $sale) {
                $gross = 0.0;
                foreach ($sale->lines as $line) {
                    $gross += $line['qty'] * (float) $prices[$line['code']];
                }
                $net = $gross / 1.18;
                if ($sale->outcome === SalePlan::NORMAL && $sale->at >= $from && $sale->at < $to) {
                    ++$m['salesCount'];
                    $m['subtotal'] += $net;
                    $m['discountTotal'] += ($net * $sale->discountPercent) / 100;
                    $m['salesTotal'] += $net * (1 - ($sale->discountPercent / 100)) * 1.18;
                }
                if ($sale->outcome !== SalePlan::NORMAL && $sale->outcomeAt >= $from && $sale->outcomeAt < $to) {
                    ++$m['voidCount'];
                    $m['voidTotal'] += $gross;
                }
                if ($sale->outcome === SalePlan::CREDIT_NOTE && $sale->outcomeAt >= $from && $sale->outcomeAt < $to) {
                    ++$m['creditNoteCount'];
                }
            }
            unset($m);
        }

        $members = [];
        foreach (['maria' => 1, 'luis' => 2] as $key => $id) {
            $members[] = ['userId' => $id, 'name' => $key, 'metrics' => $metrics[$key]];
        }

        $out = [];
        foreach (new TeamControlRules()->assess($members) as $row) {
            $out[$row['name']] = ['level' => $row['level'], 'signals' => $row['signals']];
        }

        return $out;
    }
}
