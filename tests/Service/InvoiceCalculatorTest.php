<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\EInvoicing\Service\InvoiceCalculator;
use App\EInvoicing\Service\InvoiceLineInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{lines: array<int, InvoiceLineInput>, discount: mixed, tax: mixed, charges: mixed, expectedTotal: mixed, expected: array<string, string>}>
     */
    public static function calculationProvider(): array
    {
        return [
            'single_line_basic' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.18'),
                ],
                'discount' => '0',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '1.00',
                    'discount' => '0.00',
                    'taxable' => '1.00',
                    'tax' => '0.18',
                    'charges' => '0.00',
                    'total' => '1.18',
                ],
            ],
            'multi_line_no_discount' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.18'),
                    new InvoiceLineInput('2', '1.18'),
                ],
                'discount' => '0',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '3.00',
                    'discount' => '0.00',
                    'taxable' => '3.00',
                    'tax' => '0.54',
                    'charges' => '0.00',
                    'total' => '3.54',
                ],
            ],
            'discount_10_percent' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.18'),
                    new InvoiceLineInput('1', '2.36'),
                ],
                'discount' => '10',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '3.00',
                    'discount' => '0.30',
                    'taxable' => '2.70',
                    'tax' => '0.48',
                    'charges' => '0.00',
                    'total' => '3.18',
                ],
            ],
            'charges_10_percent' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.28'),
                ],
                'discount' => '0',
                'tax' => '18',
                'charges' => '10',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '1.08',
                    'discount' => '0.00',
                    'taxable' => '1.08',
                    'tax' => '0.19',
                    'charges' => '0.11',
                    'total' => '1.38',
                ],
            ],
            'quantity_decimals' => [
                'lines' => [
                    new InvoiceLineInput('1.25', '1.18'),
                ],
                'discount' => '0',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '1.25',
                    'discount' => '0.00',
                    'taxable' => '1.25',
                    'tax' => '0.23',
                    'charges' => '0.00',
                    'total' => '1.48',
                ],
            ],
            'unit_price_099_qty3' => [
                'lines' => [
                    new InvoiceLineInput('3', '0.99'),
                ],
                'discount' => '0',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '2.52',
                    'discount' => '0.00',
                    'taxable' => '2.52',
                    'tax' => '0.45',
                    'charges' => '0.00',
                    'total' => '2.97',
                ],
            ],
            'discount_qty_decimal' => [
                'lines' => [
                    new InvoiceLineInput('2.5', '1.18'),
                ],
                'discount' => '5',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '2.50',
                    'discount' => '0.13',
                    'taxable' => '2.37',
                    'tax' => '0.43',
                    'charges' => '0.00',
                    'total' => '2.80',
                ],
            ],
            'fallback_document_level' => [
                'lines' => [],
                'discount' => '0',
                'tax' => '18',
                'charges' => '0',
                'expectedTotal' => '118.00',
                'expected' => [
                    'subtotal' => '100.00',
                    'discount' => '0.00',
                    'taxable' => '100.00',
                    'tax' => '18.00',
                    'charges' => '0.00',
                    'total' => '118.00',
                ],
            ],
        ];
    }

    /**
     * @param array<int, InvoiceLineInput> $lines
     * @param array<string, string> $expected
     */
    #[DataProvider('calculationProvider')]
    public function testCalculations(
        array $lines,
        string $discount,
        string $tax,
        string $charges,
        ?string $expectedTotal,
        array $expected
    ): void {
        $calculator = new InvoiceCalculator($lines, $discount, $tax, $charges, $expectedTotal);
        $amounts = $calculator->calculate();

        $this->assertSame($expected['subtotal'], $amounts->getSubtotal());
        $this->assertSame($expected['discount'], $amounts->getDiscountAmount());
        $this->assertSame($expected['taxable'], $amounts->getTaxedAmount());
        $this->assertSame($expected['tax'], $amounts->getTax());
        $this->assertSame($expected['charges'], $amounts->getChargesAmount());
        $this->assertSame($expected['total'], $amounts->getTotal());
    }

    public function testRoundingAdjustmentPositive(): void
    {
        $lines = [
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
        ];

        $calculator = new InvoiceCalculator($lines, '0', '18', '0', '2.37');
        $amounts = $calculator->calculate();
        $items = $amounts->getLines();

        $this->assertSame('2.37', $amounts->getTotal());
        $this->assertSame('0.18', $items[0]->taxAmount);
        $this->assertSame('0.19', $items[1]->taxAmount);
        $this->assertSame('1.19', $items[1]->totalAmount);
    }

    public function testRoundingAdjustmentNegative(): void
    {
        $lines = [
            new InvoiceLineInput('1', '1.18'),
            new InvoiceLineInput('1', '1.18'),
        ];

        $calculator = new InvoiceCalculator($lines, '0', '18', '0', '2.35');
        $amounts = $calculator->calculate();
        $items = $amounts->getLines();

        $this->assertSame('2.35', $amounts->getTotal());
        $this->assertSame('0.18', $items[0]->taxAmount);
        $this->assertSame('0.17', $items[1]->taxAmount);
        $this->assertSame('1.17', $items[1]->totalAmount);
    }
}
