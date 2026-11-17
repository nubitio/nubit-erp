<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Domain\VoucherItem;

/**
 * Builds the {@see Voucher} the SUNAT pipeline needs from an integration
 * request, without a backing Sale.
 *
 * Without `lines[]` a document is one line (`line_description`); with it, one
 * item per line. Every line is taxed at 18% IGV, cash payment, no detraction,
 * no discount, no ISC; anything more is out of scope for this channel.
 */
final class SyntheticVoucherFactory
{
    private const string IGV_PERCENT = '18.00';
    private const string TAX_TYPE_GRAVADO = '10'; // catálogo 07
    private const string OPERATION_TYPE_INTERNAL = '0101';
    private const int PAYMENT_METHOD_CASH = 1;
    private const string UOM_UNIT = 'NIU';
    private const string ITEM_CODE = 'HOSTING';

    /**
     * @param string $companyId company UUID of the tenant emitting the document
     */
    public function fromInput(DocumentInput $input, string $companyId, ?\DateTimeImmutable $issuedAt = null): Voucher
    {
        $issuedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('America/Lima'));

        $subtotal = self::minor($input->subtotalMinor);
        $tax = self::minor($input->taxMinor);
        $total = self::minor($input->totalMinor);

        $lines = [] !== $input->lines
            ? $input->lines
            : [new DocumentLine($input->lineDescription, 1, $input->subtotalMinor, $input->taxMinor, $input->totalMinor)];
        $items = array_map(self::item(...), $lines);

        return new Voucher(
            sale_id: null,
            customer_doc_type: self::customerDocCode($input),
            customer_doc_number: $input->customerDocNumber ?? '-',
            customer_name: $input->customerName,
            customer_address: $input->customerAddress ?? '-',
            customer_phone: null,
            customer_email: $input->customerEmail,
            company_id: $companyId,
            voucher_type: $input->sunatDocumentType(),
            voucher_series: $input->series,
            voucher_number: (string) $input->number,
            voucher_payment_method: self::PAYMENT_METHOD_CASH,
            voucher_date: $issuedAt->format('Y-m-d'),
            voucher_currency: $input->currency,
            voucher_exchange_rate: null,
            voucher_discount_amount: '0.00',
            voucher_discount_percentage: null,
            voucher_consumption_surcharge_amount: '0.00',
            voucher_consumption_surcharge_percentage: null,
            voucher_taxed_amount: $subtotal,
            voucher_tax_amount: $tax,
            voucher_tax_percentage: self::IGV_PERCENT,
            voucher_total_taxes: $tax,
            voucher_sale_value: $subtotal,
            voucher_sale_amount: $total,
            voucher_operation_type: self::OPERATION_TYPE_INTERNAL,
            voucher_note: $input->orderReference !== null ? 'Ref: ' . $input->orderReference : null,
            voucher_status: null,
            voucher_items: $items,
        );
    }

    private static function item(DocumentLine $line): VoucherItem
    {
        $net = self::minor($line->subtotalMinor);
        $gross = self::minor($line->totalMinor);

        return new VoucherItem(
            product_code: self::ITEM_CODE,
            product_uom: self::UOM_UNIT,
            product_name: $line->description,
            quantity: (string) $line->quantity,
            unit_value: 1 === $line->quantity ? $net : number_format($line->subtotalMinor / 100 / $line->quantity, 6, '.', ''),
            unit_price: 1 === $line->quantity ? $gross : number_format($line->totalMinor / 100 / $line->quantity, 6, '.', ''),
            discount_amount: '0.00',
            discount_percentage: null,
            taxed_amount: $net,
            tax_amount: self::minor($line->taxMinor),
            tax_percentage: self::IGV_PERCENT,
            tax_type: self::TAX_TYPE_GRAVADO,
            total: $gross,
        );
    }

    private static function minor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    /** SUNAT catálogo 06: 6 = RUC, 1 = DNI, 0 = sin documento. */
    private static function customerDocCode(DocumentInput $input): string
    {
        return match ($input->customerDocType) {
            'RUC' => '6',
            'DNI' => '1',
            default => '0',
        };
    }
}
