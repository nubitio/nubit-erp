<?php

declare(strict_types=1);

namespace App\Tests\Sales\Receivable;

use App\Sales\Receivable\CollectionSlice;
use App\Sales\Receivable\CollectionSlicer;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CollectionSlicerTest extends TestCase
{
    public function testCashAndTransferAreSpreadOverTheInvoicesInOrder(): void
    {
        // 2,500 = 1,000 cash + 1,500 transfer, settling 800 / 1,200 / 500.
        $slices = new CollectionSlicer()->slice($this->amounts('1000', '1500'), $this->amounts('800', '1200', '500'));

        self::assertSame(['t0>a0:800.00', 't0>a1:200.00', 't1>a1:1000.00', 't1>a2:500.00'], $this->describe($slices));
    }

    public function testWhatTheInvoicesDoNotAbsorbBecomesTheExcessSlice(): void
    {
        $slices = new CollectionSlicer()->slice($this->amounts('300'), $this->amounts('100'));

        self::assertSame(['t0>a0:100.00', 't0>excess:200.00'], $this->describe($slices));
    }

    public function testACollectionWithNoAllocationIsAllExcess(): void
    {
        $slices = new CollectionSlicer()->slice($this->amounts('50', '25'), []);

        self::assertSame(['t0>excess:50.00', 't1>excess:25.00'], $this->describe($slices));
    }

    public function testEveryCentOfEveryTenderLandsInExactlyOneSlice(): void
    {
        $tenders = $this->amounts('33.33', '66.67', '10.01');
        $slices = new CollectionSlicer()->slice($tenders, $this->amounts('70.00', '25.50'));

        $perTender = [];
        foreach ($slices as $slice) {
            $perTender[$slice->tenderIndex] = ($perTender[$slice->tenderIndex] ?? BigDecimal::zero())->plus($slice->amount);
        }
        foreach ($tenders as $index => $tender) {
            self::assertTrue($perTender[$index]->isEqualTo($tender), 'tender ' . $index);
        }
    }

    public function testAllocationsAboveTheTendersAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CollectionSlicer()->slice($this->amounts('100'), $this->amounts('60', '50'));
    }

    /** @return list<BigDecimal> */
    private function amounts(string ...$values): array
    {
        return array_map(static fn(string $v): BigDecimal => BigDecimal::of($v), array_values($values));
    }

    /**
     * @param list<CollectionSlice> $slices
     *
     * @return list<string>
     */
    private function describe(array $slices): array
    {
        return array_map(static fn(CollectionSlice $s): string => sprintf(
            't%d>%s:%s',
            $s->tenderIndex,
            $s->allocationIndex === null ? 'excess' : 'a' . $s->allocationIndex,
            (string) $s->amount->toScale(2),
        ), $slices);
    }
}
