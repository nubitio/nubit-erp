<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\Efact\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class InvoiceAmounts
{
    /** @var InvoiceLineAmounts[] */
    private array $lines = [];
    private string $subtotal;

    private string $taxedAmount;

    private string $discountAmount;

    private string $chargesAmount;

    private string $tax;

    private string $total;

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): InvoiceAmounts
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxedAmount(): string
    {
        return $this->taxedAmount;
    }

    public function setTaxedAmount(string $taxedAmount): InvoiceAmounts
    {
        $this->taxedAmount = $taxedAmount;

        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): InvoiceAmounts
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getChargesAmount(): string
    {
        return $this->chargesAmount;
    }

    public function setChargesAmount(string $chargesAmount): InvoiceAmounts
    {
        $this->chargesAmount = $chargesAmount;

        return $this;
    }

    public function getTax(): string
    {
        return $this->tax;
    }

    public function setTax(string $tax): InvoiceAmounts
    {
        $this->tax = $tax;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): InvoiceAmounts
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return InvoiceLineAmounts[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @param InvoiceLineAmounts[] $lines
     */
    public function setLines(array $lines): InvoiceAmounts
    {
        $this->lines = $lines;

        return $this;
    }
}
