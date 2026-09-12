<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CollectOnAccountPlanner;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CollectOnAccountPlannerTest extends TestCase
{
    private CollectOnAccountPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new CollectOnAccountPlanner();
    }

    public function testFifoFillsOldestSalesFirstAndStopsWhenTheMoneyRunsOut(): void
    {
        $plan = $this->planner->fifo([
            $this->sale('s1', '100.00'),
            $this->sale('s2', '100.00'),
            $this->sale('s3', '100.00'),
        ], BigDecimal::of('150.00'));

        static::assertCount(2, $plan);
        static::assertSame('s1', (string) $plan[0]['sale']->getId());
        static::assertSame('100.00', (string) $plan[0]['amount']);
        static::assertSame('s2', (string) $plan[1]['sale']->getId());
        static::assertSame('50.00', (string) $plan[1]['amount']);
    }

    public function testFifoLeavesAShortfallWhenTheAmountExceedsTotalDebt(): void
    {
        $plan = $this->planner->fifo([
            $this->sale('s1', '40.00'),
            $this->sale('s2', '30.00'),
        ], BigDecimal::of('200.00'));

        $applied = array_reduce(
            $plan,
            static fn(string $c, array $r): string => (string) BigDecimal::of($c)->plus($r['amount']),
            initial: '0',
        );
        static::assertSame('70.00', $applied);
    }

    public function testExplicitSplitIsHonouredInOrder(): void
    {
        $plan = $this->planner->explicit([$this->sale('s1', '100.00'), $this->sale('s2', '100.00')], [
            ['saleId' => 's2', 'amount' => '30.00'],
            ['saleId' => 's1', 'amount' => '100.00'],
        ]);

        static::assertSame(['s2', 's1'], array_map(static fn(array $r): string => (string) $r['sale']->getId(), $plan));
        static::assertSame(['30.00', '100.00'], array_map(static fn(array $r): string => (string) $r['amount'], $plan));
    }

    public function testExplicitRejectsASaleThatIsNotOpenForTheCustomer(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->planner->explicit([$this->sale('s1', '100.00')], [['saleId' => 'other', 'amount' => '10.00']]);
    }

    public function testExplicitRejectsAnAmountAboveTheSaleBalance(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->planner->explicit([$this->sale('s1', '50.00')], [['saleId' => 's1', 'amount' => '50.01']]);
    }

    public function testExplicitRejectsADuplicatedSale(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->planner->explicit([$this->sale('s1', '100.00')], [
            ['saleId' => 's1', 'amount' => '10.00'],
            ['saleId' => 's1', 'amount' => '10.00'],
        ]);
    }

    public function testExplicitRejectsANonPositiveAmount(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->planner->explicit([$this->sale('s1', '100.00')], [['saleId' => 's1', 'amount' => '0']]);
    }

    private function sale(string $id, string $balanceDue): SaleRecord
    {
        $sale = new SaleRecord()
            ->setBalanceDue($balanceDue)
            ->setCurrency('PEN');
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, $id);

        return $sale;
    }
}
