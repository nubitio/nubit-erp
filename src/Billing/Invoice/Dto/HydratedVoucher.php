<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Dto;

use DateTimeImmutable;
use Greenter\Model\Company\Address;

readonly class HydratedVoucher
{
    public function __construct(
        public string $saleId,
        public string $customerDocType,
        public string $customerDocNumber,
        public string $customerName,
        public Address $customerAddress,
        public ?string $customerPhone,
        public ?string $customerEmail,
        public ?string $companyId,
        public ?string $companyName,
        public ?string $companyAddress,
        public ?string $companyPhone,
        public ?string $branchAddress,
        public ?string $branchPhone,
        public string $voucherType,
        public string $voucherSeries,
        public string $voucherNumber,
        public int $voucherPaymentMethod,
        public DateTimeImmutable $voucherDate,
        public string $voucherCurrency,
        public ?float $voucherExchangeRate,
        public float $voucherDiscountAmount,
        public ?float $voucherDiscountPercentage,
        public float $voucherDiscountTotal,
        public float $voucherConsumptionSurchargeAmount,
        public ?float $voucherConsumptionSurchargePercentage,
        public float $voucherTaxedAmount,
        public float $voucherTaxAmount,
        public ?float $voucherTaxPercentage,
        public float $voucherTotalTaxes,
        public float $voucherSaleValue,
        public float $voucherSaleAmount,
        public string $voucherOperationType,
        public ?string $voucherDetractionCode,
        public ?float $voucherDetractionPercent,
        public ?float $voucherDetractionAmount,
        public ?string $voucherDetractionPaymentMethod,
        public ?string $voucherDetractionPaymentDescription,
        public ?float $voucherDetractionValueRef,
        public ?string $voucherDetractionAccount,
        public ?string $voucherDetractionDescription,
        public ?string $voucherBankName,
        public ?string $voucherBankAccount,
        public ?string $voucherBankCci,
        public ?string $voucherNote,
        public ?string $voucherHash,
        public ?string $voucherQrData,
        public ?int $voucherStatus,
        /** @var HydratedVoucherItem[] */
        public array $voucherItems,
    ) {}
}
