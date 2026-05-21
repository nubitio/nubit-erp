<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Dto;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Domain\VoucherItem;
use App\Billing\Service\InvoiceAmounts;

final class VoucherHeaderDto
{
    public function __construct(
        public ?string $saleId = null,
        public ?string $customerDocType = null,
        public ?string $customerDocNumber = null,
        public ?string $customerName = null,
        public ?string $customerAddress = null,
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public ?string $companyId = null,
        public ?string $companyName = null,
        public ?string $companyAddress = null,
        public ?string $companyPhone = null,
        public ?string $branchAddress = null,
        public ?string $branchPhone = null,
        public ?string $voucherType = null,
        public ?string $voucherSeries = null,
        public ?string $voucherNumber = null,
        public ?int $voucherPaymentMethod = null,
        public ?string $voucherPaymentMethodName = null,
        public ?string $voucherDate = null,
        public ?string $voucherCurrency = null,
        public ?string $voucherExchangeRate = null,
        public ?string $voucherDiscountAmount = null,
        public ?string $voucherDiscountPercentage = null,
        public ?string $voucherConsumptionSurchargeAmount = null,
        public ?string $voucherConsumptionSurchargePercentage = null,
        public ?string $voucherTaxedAmount = null,
        public ?string $voucherTaxAmount = null,
        public ?string $voucherTaxPercentage = null,
        public ?string $voucherTotalTaxes = null,
        public ?string $voucherSaleValue = null,
        public ?string $voucherSaleAmount = null,
        public ?string $voucherOperationType = null,
        public ?string $voucherDetractionCode = null,
        public ?string $voucherDetractionPercent = null,
        public ?string $voucherDetractionAmount = null,
        public ?string $voucherDetractionPaymentMethod = null,
        public ?string $voucherDetractionPaymentDescription = null,
        public ?string $voucherDetractionValueRef = null,
        public ?string $voucherDetractionAccount = null,
        public ?string $voucherDetractionDescription = null,
        public ?string $voucherBankName = null,
        public ?string $voucherBankAccount = null,
        public ?string $voucherBankCci = null,
        public ?string $voucherNote = null,
        public ?string $voucherHash = null,
        public ?string $voucherQrData = null,
        public ?int $voucherStatus = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            saleId: isset($row['sale_id']) ? (string) $row['sale_id'] : null,
            customerDocType: isset($row['customer_doc_type']) ? (string) $row['customer_doc_type'] : null,
            customerDocNumber: isset($row['customer_doc_number']) ? (string) $row['customer_doc_number'] : null,
            customerName: isset($row['customer_name']) ? (string) $row['customer_name'] : null,
            customerAddress: isset($row['customer_address']) ? (string) $row['customer_address'] : null,
            customerEmail: isset($row['customer_email']) ? (string) $row['customer_email'] : null,
            customerPhone: isset($row['customer_phone']) ? (string) $row['customer_phone'] : null,
            companyId: isset($row['company_id']) ? (string) $row['company_id'] : null,
            companyName: isset($row['company_name']) ? (string) $row['company_name'] : null,
            companyAddress: isset($row['company_address']) ? (string) $row['company_address'] : null,
            companyPhone: isset($row['company_phone']) ? (string) $row['company_phone'] : null,
            branchAddress: isset($row['branch_address']) ? (string) $row['branch_address'] : null,
            branchPhone: isset($row['branch_phone']) ? (string) $row['branch_phone'] : null,
            voucherType: isset($row['voucher_type']) ? (string) $row['voucher_type'] : null,
            voucherSeries: isset($row['voucher_series']) ? (string) $row['voucher_series'] : null,
            voucherNumber: isset($row['voucher_number']) ? (string) $row['voucher_number'] : null,
            voucherPaymentMethod: isset($row['voucher_payment_method']) ? (int) $row['voucher_payment_method'] : null,
            voucherPaymentMethodName: self::stringOrNull($row, 'voucher_payment_method_name'),
            voucherDate: isset($row['voucher_date']) ? (string) $row['voucher_date'] : null,
            voucherCurrency: isset($row['voucher_currency']) ? (string) $row['voucher_currency'] : null,
            voucherExchangeRate: isset($row['voucher_exchange_rate']) ? (string) $row['voucher_exchange_rate'] : null,
            voucherDiscountAmount: self::stringOrNull($row, 'voucher_discount_amount'),
            voucherDiscountPercentage: self::stringOrNull($row, 'voucher_discount_percentage'),
            voucherConsumptionSurchargeAmount: self::stringOrNull($row, 'voucher_consumption_surcharge_amount'),
            voucherConsumptionSurchargePercentage: self::stringOrNull(
                $row,
                'voucher_consumption_surcharge_percentage'
            ),
            voucherTaxedAmount: isset($row['voucher_taxed_amount']) ? (string) $row['voucher_taxed_amount'] : null,
            voucherTaxAmount: isset($row['voucher_tax_amount']) ? (string) $row['voucher_tax_amount'] : null,
            voucherTaxPercentage: self::stringOrNull($row, 'voucher_tax_percentage'),
            voucherTotalTaxes: isset($row['voucher_total_taxes']) ? (string) $row['voucher_total_taxes'] : null,
            voucherSaleValue: isset($row['voucher_sale_value']) ? (string) $row['voucher_sale_value'] : null,
            voucherSaleAmount: isset($row['voucher_sale_amount']) ? (string) $row['voucher_sale_amount'] : null,
            voucherOperationType: self::stringOrNull($row, 'voucher_operation_type'),
            voucherDetractionCode: self::stringOrNull($row, 'voucher_detraction_code'),
            voucherDetractionPercent: self::stringOrNull($row, 'voucher_detraction_percent'),
            voucherDetractionAmount: self::stringOrNull($row, 'voucher_detraction_amount'),
            voucherDetractionPaymentMethod: self::stringOrNull($row, 'voucher_detraction_payment_method'),
            voucherDetractionPaymentDescription: self::stringOrNull(
                $row,
                'voucher_detraction_payment_description'
            ),
            voucherDetractionValueRef: self::stringOrNull($row, 'voucher_detraction_value_ref'),
            voucherDetractionAccount: self::stringOrNull($row, 'voucher_detraction_account'),
            voucherDetractionDescription: self::stringOrNull($row, 'voucher_detraction_description'),
            voucherBankName: isset($row['voucher_bank_name']) ? (string) $row['voucher_bank_name'] : null,
            voucherBankAccount: isset($row['voucher_bank_account']) ? (string) $row['voucher_bank_account'] : null,
            voucherBankCci: isset($row['voucher_bank_cci']) ? (string) $row['voucher_bank_cci'] : null,
            voucherNote: isset($row['voucher_note']) ? (string) $row['voucher_note'] : null,
            voucherHash: isset($row['voucher_hash']) ? (string) $row['voucher_hash'] : null,
            voucherQrData: isset($row['voucher_qr_data']) ? (string) $row['voucher_qr_data'] : null,
            voucherStatus: isset($row['voucher_status']) ? (int) $row['voucher_status'] : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function stringOrNull(array $row, string $key): ?string
    {
        return isset($row[$key]) ? (string) $row[$key] : null;
    }

    public function applyAmounts(InvoiceAmounts $amounts): void
    {
        $this->voucherTaxedAmount = $amounts->getTaxedAmount();
        $this->voucherTaxAmount = $amounts->getTax();
        $this->voucherTotalTaxes = $amounts->getTax();
        $this->voucherConsumptionSurchargeAmount = $amounts->getChargesAmount();
        $this->voucherDiscountAmount = $amounts->getDiscountAmount();
        $this->voucherSaleValue = $amounts->getSubtotal();
        $this->voucherSaleAmount = $amounts->getTotal();
    }

    /**
     * @param array<int, VoucherItem> $items
     */
    public function toVoucher(array $items): Voucher
    {
        return new Voucher(
            sale_id: $this->saleId,
            customer_doc_type: $this->customerDocType,
            customer_doc_number: $this->customerDocNumber,
            customer_name: $this->customerName,
            customer_address: $this->customerAddress,
            customer_phone: $this->customerPhone,
            customer_email: $this->customerEmail,
            company_id: $this->companyId,
            company_name: $this->companyName,
            company_address: $this->companyAddress,
            company_phone: $this->companyPhone,
            branch_address: $this->branchAddress,
            branch_phone: $this->branchPhone,
            voucher_type: $this->voucherType,
            voucher_series: $this->voucherSeries,
            voucher_number: $this->voucherNumber,
            voucher_payment_method: $this->voucherPaymentMethod,
            voucher_payment_method_name: $this->voucherPaymentMethodName,
            voucher_date: $this->voucherDate,
            voucher_currency: $this->voucherCurrency,
            voucher_exchange_rate: $this->voucherExchangeRate,
            voucher_discount_amount: $this->voucherDiscountAmount,
            voucher_discount_percentage: $this->voucherDiscountPercentage,
            voucher_consumption_surcharge_amount: $this->voucherConsumptionSurchargeAmount,
            voucher_consumption_surcharge_percentage: $this->voucherConsumptionSurchargePercentage,
            voucher_taxed_amount: $this->voucherTaxedAmount,
            voucher_tax_amount: $this->voucherTaxAmount,
            voucher_tax_percentage: $this->voucherTaxPercentage,
            voucher_total_taxes: $this->voucherTotalTaxes,
            voucher_sale_value: $this->voucherSaleValue,
            voucher_sale_amount: $this->voucherSaleAmount,
            voucher_operation_type: $this->voucherOperationType,
            voucher_detraction_code: $this->voucherDetractionCode,
            voucher_detraction_percent: $this->voucherDetractionPercent,
            voucher_detraction_amount: $this->voucherDetractionAmount,
            voucher_detraction_payment_method: $this->voucherDetractionPaymentMethod,
            voucher_detraction_payment_description: $this->voucherDetractionPaymentDescription,
            voucher_detraction_value_ref: $this->voucherDetractionValueRef,
            voucher_detraction_account: $this->voucherDetractionAccount,
            voucher_detraction_description: $this->voucherDetractionDescription,
            voucher_bank_name: $this->voucherBankName,
            voucher_bank_account: $this->voucherBankAccount,
            voucher_bank_cci: $this->voucherBankCci,
            voucher_note: $this->voucherNote,
            voucher_hash: $this->voucherHash,
            voucher_qr_data: $this->voucherQrData,
            voucher_status: $this->voucherStatus,
            voucher_items: $items,
        );
    }
}
