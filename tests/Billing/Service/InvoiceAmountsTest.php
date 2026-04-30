<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceAmounts;
use App\Billing\Service\InvoiceLineAmounts;
use PHPUnit\Framework\TestCase;

final class InvoiceAmountsTest extends TestCase
{
    public function testFluentSettersAndGetters(): void
    {
        $amounts = new InvoiceAmounts();
        $result = $amounts
            ->setSubtotal('100.00')
            ->setTaxedAmount('84.75')
            ->setDiscountAmount('5.00')
            ->setChargesAmount('2.00')
            ->setTax('15.25')
            ->setTotal('97.00');

        self::assertSame($amounts, $result);
        self::assertSame('100.00', $amounts->getSubtotal());
        self::assertSame('84.75', $amounts->getTaxedAmount());
        self::assertSame('5.00', $amounts->getDiscountAmount());
        self::assertSame('2.00', $amounts->getChargesAmount());
        self::assertSame('15.25', $amounts->getTax());
        self::assertSame('97.00', $amounts->getTotal());
    }

    public function testLinesCollection(): void
    {
        $line = new InvoiceLineAmounts(
            quantity: '2',
            unitPrice: '50.00',
            unitValue: '42.37',
            baseAmount: '84.75',
            discountAmount: '0.00',
            taxableAmount: '84.75',
            chargesAmount: '0.00',
            taxAmount: '15.25',
            totalAmount: '100.00',
        );

        $amounts = new InvoiceAmounts();
        $amounts->setLines([$line]);

        self::assertCount(1, $amounts->getLines());
        self::assertSame($line, $amounts->getLines()[0]);
    }
}

final class InvoiceLineAmountsTest extends TestCase
{
    public function testConstructorExposesAllFields(): void
    {
        $line = new InvoiceLineAmounts(
            quantity: '3',
            unitPrice: '59.00',
            unitValue: '50.00',
            baseAmount: '150.00',
            discountAmount: '10.00',
            taxableAmount: '140.00',
            chargesAmount: '0.00',
            taxAmount: '25.20',
            totalAmount: '165.20',
        );

        self::assertSame('3', $line->quantity);
        self::assertSame('59.00', $line->unitPrice);
        self::assertSame('50.00', $line->unitValue);
        self::assertSame('150.00', $line->baseAmount);
        self::assertSame('10.00', $line->discountAmount);
        self::assertSame('140.00', $line->taxableAmount);
        self::assertSame('0.00', $line->chargesAmount);
        self::assertSame('25.20', $line->taxAmount);
        self::assertSame('165.20', $line->totalAmount);
    }
}
