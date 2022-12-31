<?php

namespace App\Core\Domain\Invoice;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class InvoiceCalculator
{
    private float $total;
    private float $discountPercentage;
    private float $taxPercentage;
    private float $chargesPercentage;

    public function __construct(
        float $total,
        float $discountPercentage,
        float $taxPercentage,
        float $chargesPercentage
    ) {
        $this->total = $total;
        $this->discountPercentage = $discountPercentage;
        $this->taxPercentage = $taxPercentage;
        $this->chargesPercentage = $chargesPercentage;
    }

    public function calculate(): InvoiceAmounts
    {
        $subtotal = $this->total / (1 + $this->taxPercentage / 100 + $this->chargesPercentage / 100);
        $taxedAmount = $subtotal - $subtotal * $this->discountPercentage / 100;
        $chargesAmount = $taxedAmount * $this->chargesPercentage / 100;
        $tax = $taxedAmount * $this->taxPercentage / 100;
        $discountAmount = $subtotal * $this->discountPercentage / 100;
        $total = $taxedAmount + $tax + $chargesAmount;

        return (new InvoiceAmounts())
            ->setSubtotal($subtotal)
            ->setTaxedAmount($taxedAmount)
            ->setDiscountAmount($discountAmount)
            ->setChargesAmount($chargesAmount)
            ->setTax($tax)
            ->setTotal($total);
    }
}
