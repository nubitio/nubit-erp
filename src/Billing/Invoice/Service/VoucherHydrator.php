<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Service;

use App\Efact\Domain\Money;
use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Dto\HydratedVoucher;
use App\Billing\Invoice\Dto\HydratedVoucherItem;
use DateTimeImmutable;
use Greenter\Model\Company\Address;

class VoucherHydrator implements VoucherHydratorInterface
{
    /**
     * @throws \DateMalformedStringException
     */
    public function hydrate(Voucher $voucher): HydratedVoucher
    {
        $moneyToFloat = static fn (mixed $value): float => Money::toFloat(Money::money($value));
        $toNullableFloat = static fn (mixed $value): ?float => $value === null || $value === '' ? null : floatval(
            $value
        );
        $toNullableInt = static fn (mixed $value): ?int => $value === null || $value === '' ? null : intval(
            $value
        );

        $voucherItems = array_map(function ($item) use ($moneyToFloat, $toNullableFloat) {
            return new HydratedVoucherItem(
                productCode: strval($item->product_code),
                productUom: $item->product_uom ?? 'NIU',
                quantity: floatval($item->quantity),
                productName: strval($item->product_name),
                unitValue: $moneyToFloat($item->unit_value),
                taxedAmount: $moneyToFloat($item->taxed_amount),
                taxPercentage: floatval($item->tax_percentage),
                taxAmount: $moneyToFloat($item->tax_amount),
                taxType: strval($item->tax_type),
                unitPrice: $moneyToFloat($item->unit_price),
                discountAmount: $moneyToFloat($item->discount_amount),
                discountPercent: $toNullableFloat($item->discount_percentage),
            );
        }, $voucher->voucher_items);

        $customerAddress = new Address()
            ->setDireccion(strval($voucher->customer_address));

        $itemDiscountTotal = array_sum(
            array_map(static fn (HydratedVoucherItem $i): float => $i->discountAmount, $voucherItems)
        );
        $globalDiscountAmount = $moneyToFloat($voucher->voucher_discount_amount);
        $voucherDiscountTotal = round($itemDiscountTotal + $globalDiscountAmount, 2);

        return new HydratedVoucher(
            saleId: strval($voucher->sale_id),
            customerDocType: strval($voucher->customer_doc_type),
            customerDocNumber: strval($voucher->customer_doc_number),
            customerName: strval($voucher->customer_name),
            customerAddress: $customerAddress,
            customerPhone: $voucher->customer_phone,
            customerEmail: $voucher->customer_email,
            companyId: $voucher->company_id,
            companyName: $voucher->company_name,
            companyAddress: $voucher->company_address,
            companyPhone: $voucher->company_phone,
            branchAddress: $voucher->branch_address,
            branchPhone: $voucher->branch_phone,
            voucherType: strval($voucher->voucher_type),
            voucherSeries: strval($voucher->voucher_series),
            voucherNumber: strval($voucher->voucher_number),
            voucherPaymentMethod: intval($voucher->voucher_payment_method),
            voucherDate: new DateTimeImmutable(strval($voucher->voucher_date)),
            voucherCurrency: strval($voucher->voucher_currency),
            voucherExchangeRate: $toNullableFloat($voucher->voucher_exchange_rate),
            voucherDiscountAmount: $globalDiscountAmount,
            voucherDiscountPercentage: $toNullableFloat($voucher->voucher_discount_percentage),
            voucherDiscountTotal: $voucherDiscountTotal,
            voucherConsumptionSurchargeAmount: $moneyToFloat($voucher->voucher_consumption_surcharge_amount ?? 0),
            voucherConsumptionSurchargePercentage: $toNullableFloat($voucher->voucher_consumption_surcharge_percentage),
            voucherTaxedAmount: $moneyToFloat($voucher->voucher_taxed_amount),
            voucherTaxAmount: $moneyToFloat($voucher->voucher_tax_amount),
            voucherTaxPercentage: $toNullableFloat($voucher->voucher_tax_percentage),
            voucherTotalTaxes: $moneyToFloat($voucher->voucher_total_taxes),
            voucherSaleValue: $moneyToFloat($voucher->voucher_sale_value),
            voucherSaleAmount: $moneyToFloat($voucher->voucher_sale_amount),
            voucherOperationType: strval($voucher->voucher_operation_type),
            voucherDetractionCode: $voucher->voucher_detraction_code,
            voucherDetractionPercent: $toNullableFloat($voucher->voucher_detraction_percent),
            voucherDetractionAmount: $toNullableFloat($voucher->voucher_detraction_amount),
            voucherDetractionPaymentMethod: $voucher->voucher_detraction_payment_method,
            voucherDetractionPaymentDescription: $voucher->voucher_detraction_payment_description,
            voucherDetractionValueRef: $toNullableFloat($voucher->voucher_detraction_value_ref),
            voucherDetractionAccount: $voucher->voucher_detraction_account,
            voucherDetractionDescription: $voucher->voucher_detraction_description,
            voucherBankName: $voucher->voucher_bank_name,
            voucherBankAccount: $voucher->voucher_bank_account,
            voucherBankCci: $voucher->voucher_bank_cci,
            voucherNote: $voucher->voucher_note,
            voucherHash: $voucher->voucher_hash,
            voucherQrData: $voucher->voucher_qr_data,
            voucherStatus: $toNullableInt($voucher->voucher_status),
            voucherItems: $voucherItems,
        );
    }
}
