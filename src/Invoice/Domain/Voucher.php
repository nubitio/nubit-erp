<?php

namespace App\Invoice\Domain;

class Voucher
{
    public function __construct(
        public ?string             $sale_id,
        public ?string             $customer_doc_type,
        public ?string             $customer_doc_number,
        public ?string             $customer_name,
        public ?string             $customer_address,
        public ?string             $customer_email,
        public ?string             $company_id,
        public ?string             $company_name,
        public ?string             $company_address,
        public ?string             $company_phone,
        public ?string             $voucher_type,
        public ?string             $voucher_series,
        public ?string             $voucher_number,
        public ?\DateTimeImmutable $voucher_date,
        public ?string             $voucher_currency,
        public ?float              $voucher_exchange_rate,
        public ?float              $voucher_discount_amount,
        public ?float              $voucher_discount_percentage,
        public ?float              $voucher_consumption_surcharge_amount,
        public ?float              $voucher_consumption_surcharge_percentage,
        public ?float              $voucher_taxed_amount,
        public ?float              $voucher_tax_amount,
        public ?float              $voucher_total_taxes,
        public ?float              $voucher_sale_value,
        public ?float              $voucher_sale_amount,
        public ?string             $voucher_note,
        public ?string             $voucher_checksum,
        public ?string             $voucher_qr_data,
        public ?int                $voucher_status,
        /** @var VoucherItem[] */
        public ?array              $voucher_items
    )
    {
    }
}