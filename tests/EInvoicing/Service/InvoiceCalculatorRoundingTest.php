<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Service;

use App\Core\Util\Money;
use App\EInvoicing\Service\InvoiceCalculator;
use App\EInvoicing\Service\InvoiceLineInput;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance tests for the receipt-line rounding fix (SLICE-006).
 *
 * Root cause: previously intermediate values (baseAmount, taxableAmount,
 * chargesAmount) were each rounded to 2 dp before the next step, so the
 * accumulated sub-cent error caused lineTotal to land 1 cent short of the
 * expected qty * unitPrice.
 *
 * Fix: keep all intermediate calculations at full precision and apply a single
 * HALF_UP round only when computing lineTotal. taxAmount is then derived as
 * the residual (lineTotal - taxableAmount - chargesAmount) so every line
 * balances exactly. SUNAT validates document-level sums, not individual line
 * IGV amounts, so absorbing the residual into taxAmount is safe.
 */
final class InvoiceCalculatorRoundingTest extends TestCase
{
    // AC-1: qty=1, unitPrice=17.00, igv=10.5% -> lineTotal=17.00

    public function testSingleQtyPrice1700WithIgv105ProducesCorrectLineTotal(): void
    {
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '17.00')],
            '0',
            '10.5',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('17.00', $lines[0]->totalAmount, 'line totalAmount must equal 17.00');
        self::assertSame('17.00', $amounts->getTotal(), 'document total must equal 17.00');
    }

    // AC-2: qty=1, unitPrice=5.00, igv=10.5% -> lineTotal=5.00

    public function testSingleQtyPrice500WithIgv105ProducesCorrectLineTotal(): void
    {
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '5.00')],
            '0',
            '10.5',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('5.00', $lines[0]->totalAmount, 'line totalAmount must equal 5.00');
        self::assertSame('5.00', $amounts->getTotal(), 'document total must equal 5.00');
    }

    // AC-3: qty=3, unitPrice=17.00, igv=10.5% -> lineTotal=51.00

    public function testQty3Price1700WithIgv105ProducesCorrectLineTotal(): void
    {
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('3', '17.00')],
            '0',
            '10.5',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('51.00', $lines[0]->totalAmount, 'line totalAmount must equal 51.00');
        self::assertSame('51.00', $amounts->getTotal(), 'document total must equal 51.00');
    }

    // AC-4: multi-line [17.00, 5.00, 25.00] at igv=10.5% -> total=47.00, no orphan cents

    public function testMultiLineSumMatchesDocumentTotal(): void
    {
        $calculator = new InvoiceCalculator(
            [
                new InvoiceLineInput('1', '17.00'),
                new InvoiceLineInput('1', '5.00'),
                new InvoiceLineInput('1', '25.00'),
            ],
            '0',
            '10.5',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(3, $lines);

        $sumFromLines = '0.00';
        foreach ($lines as $line) {
            $sumFromLines = Money::toString(
                Money::of($sumFromLines)->plus(Money::of($line->totalAmount))
            );
        }

        self::assertSame('47.00', $amounts->getTotal(), 'document total must equal 47.00');
        self::assertSame(
            $amounts->getTotal(),
            $sumFromLines,
            'sum of line totals must equal document total (no orphan cents)'
        );
    }

    // AC-5: regression -- discount > 0 still computes correctly (discount=5%)

    public function testDiscountPercentageStillAppliesCorrectly(): void
    {
        // unitPrice=100.00, qty=1, discount=5%, igv=18%
        // High-precision path:
        //   unitValue         = 100.00 / 1.18 = 84.745763...  (6dp)
        //   baseUnrounded     = 84.745763...
        //   discountUnrounded = 84.745763... * 0.05 = 4.237288...
        //   taxableUnrounded  = 80.508474...
        //   lineTotalUnrounded = 80.508474... * 1.18 = 94.999999... -> 95.00
        //   taxAmount (residual) = 95.00 - 80.51 - 0.00 = 14.49
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '100.00')],
            '5',
            '18',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        // lineTotal = taxableUnrounded * divisor -- discount reduces the total
        self::assertSame('95.00', $lines[0]->totalAmount, 'lineTotal must account for discount');
        self::assertSame('4.24', $lines[0]->discountAmount, 'discount amount must be 4.24');
        self::assertSame('80.51', $lines[0]->taxableAmount, 'taxable amount must be 80.51');
        // taxAmount is derived residual; verify the line balances exactly
        $reconstructed = Money::toString(
            Money::of($lines[0]->taxableAmount)
                ->plus(Money::of($lines[0]->chargesAmount))
                ->plus(Money::of($lines[0]->taxAmount))
        );
        self::assertSame(
            $lines[0]->totalAmount,
            $reconstructed,
            'taxable + charges + tax must equal lineTotal'
        );
    }

    public function testDiscountWithMultipleQtyBalancesCorrectly(): void
    {
        // qty=2, unitPrice=50.00, discount=5%, igv=18%
        // lineTotal = taxableUnrounded * divisor, rounded once
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('2', '50.00')],
            '5',
            '18',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        // Verify total integrity: taxable + charges + tax == lineTotal
        $reconstructed = Money::toString(
            Money::of($lines[0]->taxableAmount)
                ->plus(Money::of($lines[0]->chargesAmount))
                ->plus(Money::of($lines[0]->taxAmount))
        );
        self::assertSame($lines[0]->totalAmount, $reconstructed, 'line must balance');
        self::assertSame(
            $lines[0]->totalAmount,
            $amounts->getTotal(),
            'document total must equal single line total'
        );
    }

    // AC-6: qty=3, unitPrice=5.99, igv=18% -> total=17.97, sum-of-lines == doc total

    public function testQty3Price599WithIgv18ProducesCorrectTotal(): void
    {
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('3', '5.99')],
            '0',
            '18',
            '0',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('17.97', $lines[0]->totalAmount, 'lineTotal must equal 17.97');
        self::assertSame('17.97', $amounts->getTotal(), 'document total must equal 17.97');
    }

    // AC-7: applyRoundingAdjustment still works when expectedTotal differs by +/-1 cent

    public function testRoundingAdjustmentAppliedWhenExpectedTotalIsOneCentHigh(): void
    {
        // If an external expectedTotal is supplied and differs by exactly +1 cent,
        // applyRoundingAdjustment must absorb it into the last line.
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '17.00')],
            '0',
            '10.5',
            '0',
            '17.01',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('17.01', $lines[0]->totalAmount, 'adjustment must push last line up by 1 cent');
        self::assertSame('17.01', $amounts->getTotal(), 'document total must reflect adjusted value');
    }

    public function testRoundingAdjustmentAppliedWhenExpectedTotalIsOneCentLow(): void
    {
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '17.00')],
            '0',
            '10.5',
            '0',
            '16.99',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('16.99', $lines[0]->totalAmount, 'adjustment must push last line down by 1 cent');
        self::assertSame('16.99', $amounts->getTotal(), 'document total must reflect adjusted value');
    }

    public function testRoundingAdjustmentNotAppliedWhenDiffExceedsOneCent(): void
    {
        // A diff of more than 1 cent must be ignored by applyRoundingAdjustment.
        $calculator = new InvoiceCalculator(
            [new InvoiceLineInput('1', '17.00')],
            '0',
            '10.5',
            '0',
            '18.00',
        );

        $amounts = $calculator->calculate();
        $lines   = $amounts->getLines();

        self::assertCount(1, $lines);
        self::assertSame('17.00', $lines[0]->totalAmount, 'large diff must not be adjusted');
        self::assertSame('17.00', $amounts->getTotal(), 'document total must remain at computed value');
    }
}
