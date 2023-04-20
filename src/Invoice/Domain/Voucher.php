<?php

namespace App\Invoice\Domain;

use Symfony\Component\Serializer\Annotation\SerializedName;

class Voucher
{
    public function __construct(
        public ?string $sale_id = null,
        public ?string $customer_doc_type = null,
        public ?string $customer_doc_number = null,
        public ?string $customer_name = null,
        public ?string $customer_address = null,
        public ?string $customer_phone = null,
        public ?string $customer_email = null,
        public ?string $company_id = null,
        public ?string $company_name = null,
        public ?string $company_address = null,
        public ?string $company_phone = null,
        public ?string $voucher_type = null,
        public ?string $voucher_series = null,
        public ?string $voucher_number = null,
        public ?string $voucher_date = null,
        public ?string $voucher_currency = null,
        public ?float  $voucher_exchange_rate = null,
        public ?float  $voucher_discount_amount = null,
        public ?float  $voucher_discount_percentage = null,
        public ?float  $voucher_consumption_surcharge_amount = null,
        public ?float  $voucher_consumption_surcharge_percentage = null,
        public ?float  $voucher_taxed_amount = null,
        public ?float  $voucher_tax_amount = null,
        public ?float  $voucher_tax_percentage = null,
        public ?float  $voucher_total_taxes = null,
        public ?float  $voucher_sale_value = null,
        public ?float  $voucher_sale_amount = null,
        public ?string $voucher_note = null,
        public ?string $voucher_hash = null,
        public ?string $voucher_qr_data = null,
        public ?int    $voucher_status = null,
        /** @var VoucherItem[] */
        public array   $voucher_items = []
    )
    {
    }

    #[SerializedName('voucher_sale_amount_text')]
    public function getVoucherTotalText(): string
    {
        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $parts = explode('.', number_format($this->voucher_sale_amount, 2, '.', ''));
        $amount = $formatter->format((int)$parts[0]);
        return 'Son: ' . mb_strtoupper($amount . ' Y ' . $parts[1] . '/100');
    }
}