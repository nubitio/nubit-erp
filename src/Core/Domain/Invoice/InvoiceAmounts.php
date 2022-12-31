<?php

namespace App\Core\Domain\Invoice;

use App\Core\Domain\Annotation\IgnoreAutowire;

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

    /**
     * @return $this
     */
    public function setSubtotal(float $subtotal): InvoiceAmounts
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxedAmount(): float
    {
        return $this->taxedAmount;
    }

    /**
     * @return $this
     */
    public function setTaxedAmount(float $taxedAmount): InvoiceAmounts
    {
        $this->taxedAmount = $taxedAmount;

        return $this;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    /**
     * @return $this
     */
    public function setDiscountAmount(float $discountAmount): InvoiceAmounts
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getChargesAmount(): float
    {
        return $this->chargesAmount;
    }

    /**
     * @return $this
     */
    public function setChargesAmount(float $chargesAmount): InvoiceAmounts
    {
        $this->chargesAmount = $chargesAmount;

        return $this;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    /**
     * @return $this
     */
    public function setTax(float $tax): InvoiceAmounts
    {
        $this->tax = $tax;

        return $this;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * @return $this
     */
    public function setTotal(float $total): InvoiceAmounts
    {
        $this->total = $total;

        return $this;
    }
}
