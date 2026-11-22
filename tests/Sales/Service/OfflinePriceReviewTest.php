<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Service\OfflinePriceReview;
use PHPUnit\Framework\TestCase;

final class OfflinePriceReviewTest extends TestCase
{
    /** @return array{lineId: int, saleId: string, series: ?string, number: ?string, issuedAt: ?\DateTimeInterface, seller: ?string, product: string, quantity: string, charged: string, expected: ?string} */
    private function row(string $charged, ?string $expected, string $quantity = '1.000'): array
    {
        return [
            'lineId' => 7,
            'saleId' => 'sale-1',
            'series' => 'N001',
            'number' => '3624',
            'issuedAt' => new \DateTimeImmutable('2026-11-21 11:23:37', new \DateTimeZone('UTC')),
            'seller' => 'marta',
            'product' => 'POLLO ENTERO X KG',
            'quantity' => $quantity,
            'charged' => $charged,
            'expected' => $expected,
        ];
    }

    public function testTheMarciaCase(): void
    {
        // 25.49 kg sold at 9.80 while the current price was 10.10 (the reported case, with this week's prices).
        $line = OfflinePriceReview::present($this->row('9.8000', '10.1000', '25.490'));

        self::assertSame('N001-3624', $line['document']);
        self::assertSame('9.80', $line['chargedPrice']);
        self::assertSame('10.10', $line['expectedPrice']);
        self::assertSame('-0.30', $line['difference']);
        self::assertSame('-3.0', $line['differencePercent']);
        self::assertSame('-7.65', $line['impact']);
        self::assertSame('2026-11-21T11:23:37+00:00', $line['issuedAt']);
    }

    public function testASaleAboveTheCurrentPriceHasAPositiveImpact(): void
    {
        $line = OfflinePriceReview::present($this->row('10.4000', '10.1000', '2.000'));

        self::assertSame('0.30', $line['difference']);
        self::assertSame('0.60', $line['impact']);
    }

    public function testWithoutAnExpectedPriceThereIsNothingToCompare(): void
    {
        self::assertSame('0.00', OfflinePriceReview::present($this->row('9.8000', null))['difference']);
    }

    public function testAZeroExpectedPriceHasNoPercentage(): void
    {
        self::assertNull(OfflinePriceReview::present($this->row('1.0000', '0.0000'))['differencePercent']);
    }

    public function testADocumentWithoutSeriesOrNumberIsNotShownAsADash(): void
    {
        $row = $this->row('1.0000', '1.0000');
        $row['series'] = null;
        $row['number'] = null;

        self::assertSame('', OfflinePriceReview::present($row)['document']);
    }
}
