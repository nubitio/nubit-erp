<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Invoice\Service;

use App\EInvoicing\Invoice\Domain\Voucher;
use App\EInvoicing\Invoice\Domain\VoucherItem;
use App\EInvoicing\Invoice\Dto\HydratedVoucher;
use App\EInvoicing\Invoice\Service\VoucherHydrator;
use PHPUnit\Framework\TestCase;

final class VoucherHydratorTest extends TestCase
{
    private VoucherHydrator $sut;

    protected function setUp(): void
    {
        $this->sut = new VoucherHydrator();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function makeVoucher(array $overrides = []): Voucher
    {
        $defaults = [
            'sale_id'               => 'sale-001',
            'customer_doc_type'     => '6',
            'customer_doc_number'   => '20100000001',
            'customer_name'         => 'Test SAC',
            'customer_address'      => 'Av. Lima 123',
            'customer_phone'        => null,
            'customer_email'        => null,
            'company_id'            => '20200000001',
            'company_name'          => 'Empresa SAC',
            'company_address'       => null,
            'company_phone'         => null,
            'branch_address'        => null,
            'branch_phone'          => null,
            'voucher_type'          => '01',
            'voucher_series'        => 'F001',
            'voucher_number'        => '00000001',
            'voucher_payment_method' => 1,
            'voucher_date'          => '2025-01-15',
            'voucher_currency'      => 'PEN',
            'voucher_exchange_rate' => null,
            'voucher_discount_amount' => '0.00',
            'voucher_discount_percentage' => null,
            'voucher_consumption_surcharge_amount' => null,
            'voucher_consumption_surcharge_percentage' => null,
            'voucher_taxed_amount'  => '84.75',
            'voucher_tax_amount'    => '15.25',
            'voucher_tax_percentage' => '18.00',
            'voucher_total_taxes'   => '15.25',
            'voucher_sale_value'    => '84.75',
            'voucher_sale_amount'   => '100.00',
            'voucher_operation_type' => '0101',
            'voucher_detraction_code' => null,
            'voucher_detraction_percent' => null,
            'voucher_detraction_amount' => null,
            'voucher_detraction_payment_method' => null,
            'voucher_detraction_payment_description' => null,
            'voucher_detraction_value_ref' => null,
            'voucher_detraction_account' => null,
            'voucher_detraction_description' => null,
            'voucher_bank_name'     => null,
            'voucher_bank_account'  => null,
            'voucher_bank_cci'      => null,
            'voucher_note'          => null,
            'voucher_hash'          => null,
            'voucher_qr_data'       => null,
            'voucher_status'        => null,
            'voucher_items'         => [],
        ];

        $data = array_merge($defaults, $overrides);

        $voucher = new Voucher();
        foreach ($data as $key => $value) {
            $voucher->$key = $value;
        }

        return $voucher;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Core hydration
    // ──────────────────────────────────────────────────────────────────────────

    public function testHydrateReturnsHydratedVoucher(): void
    {
        $voucher = $this->makeVoucher();

        $result = $this->sut->hydrate($voucher);

        self::assertInstanceOf(HydratedVoucher::class, $result);
    }

    public function testHydrateMapsCoreStringFields(): void
    {
        $voucher = $this->makeVoucher();

        $result = $this->sut->hydrate($voucher);

        self::assertSame('sale-001', $result->saleId);
        self::assertSame('6', $result->customerDocType);
        self::assertSame('20100000001', $result->customerDocNumber);
        self::assertSame('Test SAC', $result->customerName);
        self::assertSame('F001', $result->voucherSeries);
        self::assertSame('00000001', $result->voucherNumber);
        self::assertSame('01', $result->voucherType);
        self::assertSame('PEN', $result->voucherCurrency);
        self::assertSame('0101', $result->voucherOperationType);
    }

    public function testHydrateSetsCustomerAddress(): void
    {
        $voucher = $this->makeVoucher(['customer_address' => 'Av. Grau 456']);

        $result = $this->sut->hydrate($voucher);

        self::assertSame('Av. Grau 456', $result->customerAddress->getDireccion());
    }

    public function testHydrateParsesVoucherDate(): void
    {
        $voucher = $this->makeVoucher(['voucher_date' => '2025-06-20']);

        $result = $this->sut->hydrate($voucher);

        self::assertSame('2025-06-20', $result->voucherDate->format('Y-m-d'));
    }

    public function testHydrateConvertsMoneyFields(): void
    {
        $voucher = $this->makeVoucher([
            'voucher_sale_amount' => '100.00',
            'voucher_taxed_amount' => '84.75',
            'voucher_tax_amount' => '15.25',
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertEqualsWithDelta(100.0, $result->voucherSaleAmount, 0.01);
        self::assertEqualsWithDelta(84.75, $result->voucherTaxedAmount, 0.01);
        self::assertEqualsWithDelta(15.25, $result->voucherTaxAmount, 0.01);
    }

    public function testHydrateHandlesNullConsumptionSurchargeAmount(): void
    {
        $voucher = $this->makeVoucher(['voucher_consumption_surcharge_amount' => null]);

        $result = $this->sut->hydrate($voucher);

        // null falls back to 0 via Money::money(0)
        self::assertEqualsWithDelta(0.0, $result->voucherConsumptionSurchargeAmount, 0.01);
    }

    public function testHydrateConvertsNullableFloatFields(): void
    {
        $voucher = $this->makeVoucher([
            'voucher_exchange_rate' => '3.85',
            'voucher_detraction_percent' => '12.00',
            'voucher_detraction_amount' => '12.00',
            'voucher_detraction_value_ref' => '100.00',
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertEqualsWithDelta(3.85, $result->voucherExchangeRate ?? 0, 0.01);
        self::assertEqualsWithDelta(12.0, $result->voucherDetractionPercent ?? 0, 0.01);
        self::assertEqualsWithDelta(12.0, $result->voucherDetractionAmount ?? 0, 0.01);
        self::assertEqualsWithDelta(100.0, $result->voucherDetractionValueRef ?? 0, 0.01);
    }

    public function testHydrateReturnsNullForEmptyStringNullableFloat(): void
    {
        $voucher = $this->makeVoucher([
            'voucher_exchange_rate' => '',
            'voucher_detraction_percent' => '',
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertNull($result->voucherExchangeRate);
        self::assertNull($result->voucherDetractionPercent);
    }

    public function testHydrateConvertsNullableIntStatus(): void
    {
        // voucher_status is ?int — pass integer directly
        $voucher = $this->makeVoucher(['voucher_status' => 1]);

        $result = $this->sut->hydrate($voucher);

        self::assertSame(1, $result->voucherStatus);
    }

    public function testHydrateReturnsNullForNullableIntWhenNull(): void
    {
        $voucher = $this->makeVoucher(['voucher_status' => null]);

        $result = $this->sut->hydrate($voucher);

        self::assertNull($result->voucherStatus);
    }

    public function testHydratePreservesZeroIntStatus(): void
    {
        // voucher_status is ?int — zero is a valid non-null int status
        $voucher = $this->makeVoucher(['voucher_status' => 0]);

        $result = $this->sut->hydrate($voucher);

        // $toNullableInt(0): 0 !== null && 0 !== '' → intval(0) = 0
        self::assertSame(0, $result->voucherStatus);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Voucher items
    // ──────────────────────────────────────────────────────────────────────────

    public function testHydrateMapsVoucherItems(): void
    {
        $item = new VoucherItem();
        $item->product_code = 'PROD-001';
        $item->product_uom  = 'NIU';
        $item->quantity     = '2';
        $item->product_name = 'Laptop';
        $item->unit_value   = '500.00';
        $item->taxed_amount = '1000.00';
        $item->tax_percentage = '18.00';
        $item->tax_amount   = '180.00';
        $item->tax_type     = '10';
        $item->unit_price   = '590.00';

        $voucher = $this->makeVoucher(['voucher_items' => [$item]]);

        $result = $this->sut->hydrate($voucher);

        self::assertCount(1, $result->voucherItems);
        $hydratedItem = $result->voucherItems[0];
        self::assertSame('PROD-001', $hydratedItem->productCode);
        self::assertSame('NIU', $hydratedItem->productUom);
        self::assertSame('Laptop', $hydratedItem->productName);
        self::assertEqualsWithDelta(2.0, $hydratedItem->quantity, 0.01);
        self::assertEqualsWithDelta(500.0, $hydratedItem->unitValue, 0.01);
    }

    public function testHydrateUsesDefaultUomWhenItemUomIsNull(): void
    {
        $item = new VoucherItem();
        $item->product_code   = 'P001';
        $item->product_uom    = null;
        $item->quantity       = '1';
        $item->product_name   = 'Widget';
        $item->unit_value     = '10.00';
        $item->taxed_amount   = '10.00';
        $item->tax_percentage = '18.00';
        $item->tax_amount     = '1.80';
        $item->tax_type       = '10';
        $item->unit_price     = '11.80';

        $voucher = $this->makeVoucher(['voucher_items' => [$item]]);

        $result = $this->sut->hydrate($voucher);

        self::assertSame('NIU', $result->voucherItems[0]->productUom);
    }

    public function testHydrateReturnsEmptyItemsArrayWhenNoItems(): void
    {
        $voucher = $this->makeVoucher(['voucher_items' => []]);

        $result = $this->sut->hydrate($voucher);

        self::assertEmpty($result->voucherItems);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Passthrough nullable string fields
    // ──────────────────────────────────────────────────────────────────────────

    public function testHydratePassesThroughNullableStringFields(): void
    {
        $voucher = $this->makeVoucher([
            'customer_phone'       => '+51987654321',
            'customer_email'       => 'test@example.com',
            'voucher_note'         => 'Urgent delivery',
            'voucher_hash'         => 'abc123hash',
            'voucher_qr_data'      => 'qr-data-here',
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertSame('+51987654321', $result->customerPhone);
        self::assertSame('test@example.com', $result->customerEmail);
        self::assertSame('Urgent delivery', $result->voucherNote);
        self::assertSame('abc123hash', $result->voucherHash);
        self::assertSame('qr-data-here', $result->voucherQrData);
    }

    public function testHydratePassesThroughNullForOptionalFields(): void
    {
        $voucher = $this->makeVoucher([
            'voucher_detraction_code'       => null,
            'voucher_detraction_account'    => null,
            'voucher_bank_name'             => null,
            'voucher_bank_account'          => null,
            'voucher_bank_cci'              => null,
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertNull($result->voucherDetractionCode);
        self::assertNull($result->voucherDetractionAccount);
        self::assertNull($result->voucherBankName);
        self::assertNull($result->voucherBankAccount);
        self::assertNull($result->voucherBankCci);
    }
}
