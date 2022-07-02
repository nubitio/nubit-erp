<?php

namespace App\Domain\Invoice;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class InvoiceAmounts
{
    private float $subtotal;
    private float $taxedAmount;
    private float $discountAmount;
    private float $chargesAmount;
    private float $tax;
    private float $total;

    /**
     * @return float
     */
    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    /**
     * @param float $subtotal
     * @return $this
     */
    public function setSubtotal(float $subtotal): InvoiceAmounts
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    /**
     * @return float
     */
    public function getTaxedAmount(): float
    {
        return $this->taxedAmount;
    }

    /**
     * @param float $taxedAmount
     * @return $this
     */
    public function setTaxedAmount(float $taxedAmount): InvoiceAmounts
    {
        $this->taxedAmount = $taxedAmount;

        return $this;
    }

    /**
     * @return float
     */
    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    /**
     * @param float $discountAmount
     * @return $this
     */
    public function setDiscountAmount(float $discountAmount): InvoiceAmounts
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    /**
     * @return float
     */
    public function getChargesAmount(): float
    {
        return $this->chargesAmount;
    }

    /**
     * @param float $chargesAmount
     * @return $this
     */
    public function setChargesAmount(float $chargesAmount): InvoiceAmounts
    {
        $this->chargesAmount = $chargesAmount;

        return $this;
    }

    /**
     * @return float
     */
    public function getTax(): float
    {
        return $this->tax;
    }

    /**
     * @param float $tax
     * @return $this
     */
    public function setTax(float $tax): InvoiceAmounts
    {
        $this->tax = $tax;

        return $this;
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * @param float $total
     * @return $this
     */
    public function setTotal(float $total): InvoiceAmounts
    {
        $this->total = $total;

        return $this;
    }
}