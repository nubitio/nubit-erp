<?php

declare(strict_types=1);

namespace App\Invoice\Domain;

use NumberFormatter;
use App\Core\Util\Money;
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
        public ?int $voucher_payment_method = null,
        public ?string $voucher_date = null,
        public ?string $voucher_currency = null,
        public ?string $voucher_exchange_rate = null,
        public ?string $voucher_discount_amount = null,
        public ?string $voucher_discount_percentage = null,
        public ?string $voucher_consumption_surcharge_amount = null,
        public ?string $voucher_consumption_surcharge_percentage = null,
        public ?string $voucher_taxed_amount = null,
        public ?string $voucher_tax_amount = null,
        public ?string $voucher_tax_percentage = null,
        public ?string $voucher_total_taxes = null,
        public ?string $voucher_sale_value = null,
        public ?string $voucher_sale_amount = null,
        public ?string $voucher_operation_type = null,
        public ?string $voucher_detraction_code = null,
        public ?string $voucher_detraction_percent = null,
        public ?string $voucher_detraction_amount = null,
        public ?string $voucher_detraction_payment_method = null,
        public ?string $voucher_detraction_payment_description = null,
        public ?string $voucher_detraction_value_ref = null,
        public ?string $voucher_detraction_account = null,
        public ?string $voucher_detraction_description = null,
        public ?string $voucher_bank_name = null,
        public ?string $voucher_bank_account = null,
        public ?string $voucher_bank_cci = null,
        public ?string $voucher_note = null,
        public ?string $voucher_hash = null,
        public ?string $voucher_qr_data = null,
        public ?int $voucher_status = null,
        /** @var VoucherItem[] */
        public array $voucher_items = [],
    ) {
    }

    #[SerializedName('voucher_sale_amount_text')]
    public function getVoucherTotalText(): string
    {
        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $amount = Money::toString(Money::money($this->voucher_sale_amount ?? 0));
        $parts = explode('.', $amount);
        $amount = $formatter->format((int) $parts[0]);

        return 'Son: ' . mb_strtoupper($amount . ' Y ' . $parts[1] . '/100');
    }
}
