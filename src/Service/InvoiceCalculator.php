<?php

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
        $subtotal = $this->total / (1 + $this->taxPercentage / 100 + $this->chargesPercentage / 100);
        $taxedAmount = $subtotal - $subtotal * $this->discountPercentage / 100;
        $chargesAmount = $taxedAmount * $this->chargesPercentage / 100;
        $tax = $taxedAmount * $this->taxPercentage / 100;
        $discountAmount = $subtotal * $this->discountPercentage / 100;
        $total = $taxedAmount + $tax + $chargesAmount;

        return new InvoiceAmounts()
            ->setSubtotal($subtotal)
            ->setTaxedAmount($taxedAmount)
            ->setDiscountAmount($discountAmount)
            ->setChargesAmount($chargesAmount)
            ->setTax($tax)
            ->setTotal($total);
    }
}
