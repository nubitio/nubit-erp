<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class InvoiceCalculator
{
    public function __construct(
        private float $total,
        private float $discountPercentage,
        private float $taxPercentage,
        private float $chargesPercentage
    ) {
    }

    public function calculate(): InvoiceAmounts
    {
        $subtotal = round($this->total / (1 + $this->taxPercentage / 100 + $this->chargesPercentage / 100), 2);
        $discountAmount = round($subtotal * $this->discountPercentage / 100, 2);
        $taxedAmount = round($subtotal - $discountAmount, 2);
        $chargesAmount = round($taxedAmount * $this->chargesPercentage / 100, 2);
        $tax = round($taxedAmount * $this->taxPercentage / 100, 2);
        $total = round($taxedAmount + $tax + $chargesAmount, 2);

        return new InvoiceAmounts()
            ->setSubtotal($subtotal)
            ->setTaxedAmount($taxedAmount)
            ->setDiscountAmount($discountAmount)
            ->setChargesAmount($chargesAmount)
            ->setTax($tax)
            ->setTotal($total);
    }
}
