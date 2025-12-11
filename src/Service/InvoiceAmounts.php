<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class InvoiceAmounts
{
    private float $subtotal;

    private float $taxedAmount;

    private float $discountAmount;

    private float $chargesAmount;

    private float $tax;

    private float $total;

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): InvoiceAmounts
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxedAmount(): float
    {
        return $this->taxedAmount;
    }

    public function setTaxedAmount(float $taxedAmount): InvoiceAmounts
    {
        $this->taxedAmount = $taxedAmount;

        return $this;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(float $discountAmount): InvoiceAmounts
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getChargesAmount(): float
    {
        return $this->chargesAmount;
    }

    public function setChargesAmount(float $chargesAmount): InvoiceAmounts
    {
        $this->chargesAmount = $chargesAmount;

        return $this;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function setTax(float $tax): InvoiceAmounts
    {
        $this->tax = $tax;

        return $this;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function setTotal(float $total): InvoiceAmounts
    {
        $this->total = $total;

        return $this;
    }
}
