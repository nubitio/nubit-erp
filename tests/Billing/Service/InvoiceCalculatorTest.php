<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Service\InvoiceCalculator;
use App\Billing\Service\InvoiceLineInput;
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
                    // Deferred-rounding: 1.084746 * 1.28 = 1.388475 -> 1.39 (previously 1.38 due to
                    // intermediate rounding of baseAmount to 1.08 before applying charges+tax).
                    'tax' => '0.20',
                    'charges' => '0.11',
                    'total' => '1.39',
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
            // ── 10.5% IGV (Peruvian non-trivial rate) ───────────────────────────────
            'igv_10_5_single_line' => [
                'lines' => [
                    new InvoiceLineInput('1', '13.00'),
                ],
                'discount' => '0',
                'tax' => '10.5',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '11.76',
                    'discount' => '0.00',
                    'taxable' => '11.76',
                    'tax' => '1.24',
                    'charges' => '0.00',
                    'total' => '13.00',
                ],
            ],
            'igv_10_5_price_7' => [
                'lines' => [
                    new InvoiceLineInput('1', '7.00'),
                ],
                'discount' => '0',
                'tax' => '10.5',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '6.33',
                    'discount' => '0.00',
                    'taxable' => '6.33',
                    'tax' => '0.67',
                    'charges' => '0.00',
                    'total' => '7.00',
                ],
            ],
            'igv_10_5_multi_line' => [
                'lines' => [
                    new InvoiceLineInput('1', '13.00'),
                    new InvoiceLineInput('2', '7.00'),
                ],
                'discount' => '0',
                'tax' => '10.5',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '24.43',
                    'discount' => '0.00',
                    'taxable' => '24.43',
                    'tax' => '2.57',
                    'charges' => '0.00',
                    'total' => '27.00',
                ],
            ],
            'igv_10_5_fallback' => [
                'lines' => [],
                'discount' => '0',
                'tax' => '10.5',
                'charges' => '0',
                'expectedTotal' => '13.00',
                'expected' => [
                    'subtotal' => '11.76',
                    'discount' => '0.00',
                    'taxable' => '11.76',
                    'tax' => '1.24',
                    'charges' => '0.00',
                    'total' => '13.00',
                ],
            ],
            // ── AC-5: item discount applied first, then global discount on net subtotal ──
            // qty=2, unitPrice=100 (tax-exclusive, tax=0%), itemDiscountPercent=10, globalDiscount=5%
            // item net = 2×100×(1-0.10) = 180.00
            // global discount = 180.00 × 0.05 = 9.00
            // total = 180.00 - 9.00 = 171.00
            'ac5_item_discount_before_global' => [
                'lines' => [
                    new InvoiceLineInput('2', '100', '10'),
                ],
                'discount' => '5',
                'tax' => '0',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '180.00',
                    'discount' => '9.00',
                    'taxable' => '171.00',
                    'tax' => '0.00',
                    'charges' => '0.00',
                    'total' => '171.00',
                ],
            ],
            // ── AC-18: zero-discount sales are unaffected — identical to existing behavior ──
            // When itemDiscountPercent=0 and globalDiscount=0, result must equal the no-discount path.
            'ac18_zero_discount_unaffected' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.18', '0'),
                    new InvoiceLineInput('2', '1.18', '0'),
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
            // ── AC-3: discountPercent=0 → discountAmount=0.00, netAmount = qty × unitPrice ──
            // qty=3, unitPrice=33.33 (tax-exclusive, tax=0%), itemDiscountPercent=0, globalDiscount=0%
            // item net = 3×33.33×(1-0.00) = 99.99
            // global discount = 0.00
            // total = 99.99
            'ac3_zero_discount_no_discount_applied' => [
                'lines' => [
                    new InvoiceLineInput('3', '33.33', '0'),
                ],
                'discount' => '0',
                'tax' => '0',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '99.99',
                    'discount' => '0.00',
                    'taxable' => '99.99',
                    'tax' => '0.00',
                    'charges' => '0.00',
                    'total' => '99.99',
                ],
            ],
            // ── AC-19: BigDecimal precision — unitPrice=33.33, qty=3, discountPercent=10 ──
            // item net = 3×33.33 = 99.99
            // item discount = 99.99 × 0.10 = 9.999 → rounded HALF_UP to 10.00
            // net after item discount = 99.99 - 10.00 = 89.99
            // global discount = 0%, tax = 0%
            // total = 89.99
            'ac19_bigdecimal_precision_33_33_qty3_discount10' => [
                'lines' => [
                    new InvoiceLineInput('3', '33.33', '10'),
                ],
                'discount' => '0',
                'tax' => '0',
                'charges' => '0',
                'expectedTotal' => null,
                'expected' => [
                    'subtotal' => '89.99',
                    'discount' => '0.00',
                    'taxable' => '89.99',
                    'tax' => '0.00',
                    'charges' => '0.00',
                    'total' => '89.99',
                ],
            ],
            // ── AC-18 variant: item discount=0 but global discount present — global still applies ──
            'ac18_zero_item_discount_global_still_applies' => [
                'lines' => [
                    new InvoiceLineInput('1', '1.18', '0'),
                    new InvoiceLineInput('1', '2.36', '0'),
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
