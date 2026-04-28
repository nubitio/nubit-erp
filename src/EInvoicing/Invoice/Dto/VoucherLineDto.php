<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Dto;

use App\EInvoicing\Invoice\Domain\VoucherItem;
use App\EInvoicing\Service\InvoiceLineAmounts;

final class VoucherLineDto
{
    public function __construct(
        public ?string $productId = null,
        public ?string $productCode = null,
        public ?string $productUom = null,
        public ?string $productName = null,
        public ?string $quantity = null,
        public ?string $unitValue = null,
        public ?string $unitPrice = null,
        public ?string $discountAmount = null,
        public ?string $discountPercentage = null,
        public ?string $taxedAmount = null,
        public ?string $taxAmount = null,
        public ?string $taxPercentage = null,
        public ?string $taxType = null,
        public ?string $total = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            productId: isset($row['product_id']) ? (string) $row['product_id'] : null,
            productCode: isset($row['product_code']) ? (string) $row['product_code'] : null,
            productUom: isset($row['product_uom']) ? (string) $row['product_uom'] : null,
            productName: isset($row['product_name']) ? (string) $row['product_name'] : null,
            quantity: isset($row['quantity']) ? (string) $row['quantity'] : null,
            unitValue: isset($row['unit_value']) ? (string) $row['unit_value'] : null,
            unitPrice: isset($row['unit_price']) ? (string) $row['unit_price'] : null,
            discountAmount: isset($row['discount_amount']) ? (string) $row['discount_amount'] : null,
            discountPercentage: isset($row['discount_percentage']) ? (string) $row['discount_percentage'] : null,
            taxedAmount: isset($row['taxed_amount']) ? (string) $row['taxed_amount'] : null,
            taxAmount: isset($row['tax_amount']) ? (string) $row['tax_amount'] : null,
            taxPercentage: isset($row['tax_percentage']) ? (string) $row['tax_percentage'] : null,
            taxType: isset($row['tax_type']) ? (string) $row['tax_type'] : null,
            total: isset($row['total']) ? (string) $row['total'] : null,
        );
    }

    public function applyAmounts(InvoiceLineAmounts $amounts): void
    {
        $this->unitValue = $amounts->unitValue;
        $this->unitPrice = $amounts->unitPrice;
        $this->discountAmount = $amounts->discountAmount;
        $this->taxedAmount = $amounts->taxableAmount;
        $this->taxAmount = $amounts->taxAmount;
        $this->total = $amounts->totalAmount;
    }

    public function toVoucherItem(): VoucherItem
    {
        return new VoucherItem(
            product_id: $this->productId,
            product_code: $this->productCode,
            product_uom: $this->productUom,
            product_name: $this->productName,
            quantity: $this->quantity,
            unit_value: $this->unitValue,
            unit_price: $this->unitPrice,
            discount_amount: $this->discountAmount,
            discount_percentage: $this->discountPercentage,
            taxed_amount: $this->taxedAmount,
            tax_amount: $this->taxAmount,
            tax_percentage: $this->taxPercentage,
            tax_type: $this->taxType,
            total: $this->total,
        );
    }
}
