<?php

declare(strict_types=1);

namespace App\Tests\Efact\Domain\Finance;

use App\Efact\Domain\Finance\Allocation;
use App\Efact\Domain\Finance\AllocationPlanner;
use App\Efact\Domain\Finance\AllocationStrategy;
use App\Efact\Domain\Finance\ManualAllocationPlanner;
use App\Efact\Domain\Finance\OpenItem;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AllocationPlannerTest extends TestCase
{
    public function testTheReferenceExampleLeavesThreeApplicationsAndAUntouchedInvoice(): void
    {
        $result = new AllocationPlanner()->plan([
            $this->item('A', '800', '2026-10-01'),
            $this->item('B', '1200', '2026-10-05'),
            $this->item('C', '2400', '2026-10-10'),
            $this->item('D', '1400', '2026-10-20'),
        ], BigDecimal::of('2500'));

        self::assertSame(['A:800.00', 'B:1200.00', 'C:500.00'], $this->describe($result->allocations));
        self::assertTrue($result->unapplied->isZero());
    }

    public function testTheOldestDueDateGoesFirstEvenWhenItWasIssuedLater(): void
    {
        $items = [
            $this->item('issued-first', '100', '2026-12-01', '2026-09-01'),
            $this->item('due-first', '100', '2026-10-01', '2026-10-01'),
        ];

        $byDue = new AllocationPlanner()->plan($items, BigDecimal::of('100'), AllocationStrategy::OldestDue);
        $byIssue = new AllocationPlanner()->plan($items, BigDecimal::of('100'), AllocationStrategy::OldestIssued);

        self::assertSame(['due-first:100.00'], $this->describe($byDue->allocations));
        self::assertSame(['issued-first:100.00'], $this->describe($byIssue->allocations));
    }

    public function testWhatTheItemsCannotAbsorbIsReturnedAsUnapplied(): void
    {
        $result = new AllocationPlanner()->plan([$this->item('A', '40', '2026-10-01')], BigDecimal::of('100'));

        self::assertSame(['A:40.00'], $this->describe($result->allocations));
        self::assertSame('60.00', (string) $result->unapplied);
    }

    public function testItemsWithNothingOpenAreSkipped(): void
    {
        $result = new AllocationPlanner()->plan([
            $this->item('paid', '0', '2026-10-01'),
            $this->item('open', '50', '2026-10-02'),
        ], BigDecimal::of('30'));

        self::assertSame(['open:30.00'], $this->describe($result->allocations));
    }

    public function testTiesAreBrokenByIdSoTheSplitIsReproducible(): void
    {
        $result = new AllocationPlanner()->plan([
            $this->item('b', '10', '2026-10-01'),
            $this->item('a', '10', '2026-10-01'),
        ], BigDecimal::of('10'));

        self::assertSame(['a:10.00'], $this->describe($result->allocations));
    }

    public function testCentsAreNeverLost(): void
    {
        $result = new AllocationPlanner()->plan([
            $this->item('A', '33.33', '2026-10-01'),
            $this->item('B', '33.33', '2026-10-02'),
            $this->item('C', '33.34', '2026-10-03'),
        ], BigDecimal::of('100.00'));

        $total = BigDecimal::zero();
        foreach ($result->allocations as $allocation) {
            $total = $total->plus($allocation->amount);
        }
        self::assertSame('100.00', (string) $total->plus($result->unapplied)->toScale(2));
    }

    public function testAManualSplitIsKeptAndTheDifferenceIsUnapplied(): void
    {
        $result = new ManualAllocationPlanner()->plan(
            [$this->item('A', '100', '2026-10-01'), $this->item('B', '100', '2026-10-02')],
            BigDecimal::of('150'),
            [['itemId' => 'B', 'amount' => BigDecimal::of('100')], ['itemId' => 'A', 'amount' => BigDecimal::of('20')]],
        );

        self::assertSame(['B:100.00', 'A:20.00'], $this->describe($result->allocations));
        self::assertSame('30.00', (string) $result->unapplied);
    }

    public function testAManualLineAboveWhatTheItemOwesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only owes');

        new ManualAllocationPlanner()->plan([$this->item('A', '100', '2026-10-01')], BigDecimal::of('500'), [[
            'itemId' => 'A',
            'amount' => BigDecimal::of('101'),
        ]]);
    }

    public function testAManualSplitAboveTheAmountReceivedIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the amount received');

        new ManualAllocationPlanner()->plan(
            [$this->item('A', '100', '2026-10-01'), $this->item('B', '100', '2026-10-02')],
            BigDecimal::of('150'),
            [
                ['itemId' => 'A', 'amount' => BigDecimal::of('100')],
                ['itemId' => 'B', 'amount' => BigDecimal::of('100')],
            ],
        );
    }

    public function testADuplicatedOrUnknownManualItemIsRejected(): void
    {
        $planner = new ManualAllocationPlanner();
        $items = [$this->item('A', '100', '2026-10-01')];

        $this->expectException(InvalidArgumentException::class);
        $planner->plan($items, BigDecimal::of('50'), [['itemId' => 'Z', 'amount' => BigDecimal::of('10')]]);
    }

    public function testANonPositiveAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AllocationPlanner()->plan([], BigDecimal::of('0'));
    }

    /** @param list<Allocation> $allocations @return list<string> */
    private function describe(array $allocations): array
    {
        return array_map(static fn(Allocation $a): string => $a->itemId . ':' . $a->amount->toScale(2), $allocations);
    }

    private function item(string $id, string $open, string $due, ?string $issued = null): OpenItem
    {
        return new OpenItem(
            $id,
            new DateTimeImmutable($due),
            new DateTimeImmutable($issued ?? $due),
            BigDecimal::of($open),
        );
    }
}
