<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\Efact\Annotation\IgnoreAutowire;
use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

#[IgnoreAutowire]
readonly class InvoiceCalculator
{
    public function __construct(
        /** @var InvoiceLineInput[] */
        private array $lines,
        private string $discountPercentage,
        private string $taxPercentage,
        private string $chargesPercentage,
        private ?string $expectedTotal = null,
    ) {
    }

    /**
     * @return InvoiceAmounts
     * @throws MathException
     */
    public function calculate(): InvoiceAmounts
    {
        if ($this->lines === []) {
            return $this->calculateDocumentFallback();
        }

        $taxRate = Money::rate($this->taxPercentage);
        $chargesRate = Money::rate($this->chargesPercentage);
        $discountRate = Money::rate($this->discountPercentage);
        $divisor = BigDecimal::one()->plus($taxRate);

        $lines = [];
        $subtotal = BigDecimal::zero();
        $discountTotal = BigDecimal::zero();
        $taxableTotal = BigDecimal::zero();
        $chargesTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        foreach ($this->lines as $line) {
            $qty = Money::of($line->quantity);
            $unitPrice = Money::money($line->unitPrice);
            $unitValue = Money::div($unitPrice, $divisor, 6);

            $baseAmount = Money::money($unitValue->multipliedBy($qty));
            $discountAmount = Money::money($baseAmount->multipliedBy($discountRate));
            $taxableAmount = Money::money($baseAmount->minus($discountAmount));
            $chargesAmount = Money::money($taxableAmount->multipliedBy($chargesRate));

            // Compute lineTotal from 6dp intermediates (before any 2dp rounding) to avoid
            // cascade rounding errors on non-trivial tax rates such as 10.5%.
            // taxAmount is then the residual so that
            //   taxableAmount + chargesAmount + taxAmount = lineTotal exactly.
            $baseAmount6   = $unitValue->multipliedBy($qty);
            $discount6     = $baseAmount6->multipliedBy($discountRate);
            $taxable6      = $baseAmount6->minus($discount6);
            $lineTotal     = Money::money($taxable6->multipliedBy(BigDecimal::one()->plus($chargesRate)->plus($taxRate)));
            $taxAmount     = Money::money($lineTotal->minus($chargesAmount)->minus($taxableAmount));

            $lines[] = new InvoiceLineAmounts(
                Money::toString($qty, 6),
                Money::toString($unitPrice),
                Money::toString($unitValue, 6),
                Money::toString($baseAmount),
                Money::toString($discountAmount),
                Money::toString($taxableAmount),
                Money::toString($chargesAmount),
                Money::toString($taxAmount),
                Money::toString($lineTotal),
            );

            $subtotal = $subtotal->plus($baseAmount);
            $discountTotal = $discountTotal->plus($discountAmount);
            $taxableTotal = $taxableTotal->plus($taxableAmount);
            $chargesTotal = $chargesTotal->plus($chargesAmount);
            $taxTotal = $taxTotal->plus($taxAmount);
            $total = $total->plus($lineTotal);
        }

        $lines = $this->applyRoundingAdjustment($lines, $total);
        $total = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();

        foreach ($lines as $line) {
            $taxTotal = $taxTotal->plus(Money::of($line->taxAmount));
            $total = $total->plus(Money::of($line->totalAmount));
        }

        return new InvoiceAmounts()
            ->setSubtotal(Money::toString($subtotal))
            ->setTaxedAmount(Money::toString($taxableTotal))
            ->setDiscountAmount(Money::toString($discountTotal))
            ->setChargesAmount(Money::toString($chargesTotal))
            ->setTax(Money::toString($taxTotal))
            ->setTotal(Money::toString($total))
            ->setLines($lines);
    }

    private function calculateDocumentFallback(): InvoiceAmounts
    {
        $taxRate = Money::rate($this->taxPercentage);
        $chargesRate = Money::rate($this->chargesPercentage);
        $discountRate = Money::rate($this->discountPercentage);
        $divisor = BigDecimal::one()->plus($taxRate);
        $grossTotal = Money::money($this->expectedTotal ?? 0);

        $subtotal6      = Money::div($grossTotal, $divisor, 6);
        $subtotal       = Money::money($subtotal6);
        $discountAmount = Money::money($subtotal->multipliedBy($discountRate));
        $taxableAmount  = Money::money($subtotal->minus($discountAmount));
        $chargesAmount  = Money::money($taxableAmount->multipliedBy($chargesRate));

        // Same deferred-rounding fix as in calculate(): round total once at 6dp, derive taxAmount.
        $taxable6  = $subtotal6->minus($subtotal6->multipliedBy($discountRate));
        $total     = Money::money($taxable6->multipliedBy(BigDecimal::one()->plus($chargesRate)->plus($taxRate)));
        $taxAmount = Money::money($total->minus($chargesAmount)->minus($taxableAmount));

        return new InvoiceAmounts()
            ->setSubtotal(Money::toString($subtotal))
            ->setTaxedAmount(Money::toString($taxableAmount))
            ->setDiscountAmount(Money::toString($discountAmount))
            ->setChargesAmount(Money::toString($chargesAmount))
            ->setTax(Money::toString($taxAmount))
            ->setTotal(Money::toString($total))
            ->setLines([]);
    }

    /**
     * @param InvoiceLineAmounts[] $lines
     * @return InvoiceLineAmounts[]
     */
    private function applyRoundingAdjustment(array $lines, BigDecimal $computedTotal): array
    {
        if ($this->expectedTotal === null || $lines === []) {
            return $lines;
        }

        $expectedTotal = Money::money($this->expectedTotal);
        $diff = $expectedTotal->minus($computedTotal)->toScale(Money::SCALE, RoundingMode::HALF_UP);
        $diffCents = Money::toMinor($diff);

        if (!in_array($diffCents, [-1, 1], true)) {
            return $lines;
        }

        $lastIndex = count($lines) - 1;
        $lastLine = $lines[$lastIndex];
        $adjustment = Money::money($diff);
        $newTax = Money::money(Money::of($lastLine->taxAmount)->plus($adjustment));
        $newTotal = Money::money(Money::of($lastLine->totalAmount)->plus($adjustment));

        $lines[$lastIndex] = new InvoiceLineAmounts(
            $lastLine->quantity,
            $lastLine->unitPrice,
            $lastLine->unitValue,
            $lastLine->baseAmount,
            $lastLine->discountAmount,
            $lastLine->taxableAmount,
            $lastLine->chargesAmount,
            Money::toString($newTax),
            Money::toString($newTotal),
        );

        return $lines;
    }
}
