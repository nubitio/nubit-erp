<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CollectOnAccountPlanner;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** The example from the finance audit: one 2,500 collection over four open invoices. */
final class CollectionPlanGuardTest extends TestCase
{
    public function testTheReferenceExampleSplitsIntoThreeApplicationsAndLeavesTheLastUntouched(): void
    {
        $sales = [
            $this->sale('A', '800.00'),
            $this->sale('B', '1200.00'),
            $this->sale('C', '2400.00'),
            $this->sale('D', '1400.00'),
        ];

        $plan = new CollectOnAccountPlanner()->plan($sales, BigDecimal::of('2500.00'), []);

        self::assertCount(3, $plan);
        self::assertSame(['800.00', '1200.00', '500.00'], array_map(
            static fn(array $row): string => (string) $row['amount']->toScale(2),
            $plan,
        ));
    }

    public function testAnAmountAboveTheDebtIsRejectedWithTheHint(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('no está disponible');

        new CollectOnAccountPlanner()->plan(
            [$this->sale('A', '40.00')],
            BigDecimal::of('100.00'),
            [],
            'Esto no está disponible.',
        );
    }

    public function testAPlanMixingCurrenciesIsRejected(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('una sola moneda');

        new CollectOnAccountPlanner()->plan(
            [$this->sale('A', '50.00', 'PEN'), $this->sale('B', '50.00', 'USD')],
            BigDecimal::of('100.00'),
            [],
        );
    }

    private function sale(string $id, string $balance, string $currency = 'PEN'): SaleRecord
    {
        $sale = $this->createPartialMock(SaleRecord::class, ['getId']);
        $sale->method('getId')->willReturn($id);
        $sale->setBalanceDue($balance);
        $sale->setCurrency($currency);

        return $sale;
    }
}
