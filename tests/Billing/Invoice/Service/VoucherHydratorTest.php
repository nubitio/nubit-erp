<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Service;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Domain\VoucherItem;
use App\Billing\Invoice\Dto\HydratedVoucher;
use App\Billing\Invoice\Service\VoucherHydrator;
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
        // voucher_date is stored in UTC; midnight UTC converts to 2025-06-19 19:00 Lima (UTC-5)
        $voucher = $this->makeVoucher(['voucher_date' => '2025-06-20 00:00:00']);

        $result = $this->sut->hydrate($voucher);

        self::assertSame('2025-06-19', $result->voucherDate->format('Y-m-d'));
        self::assertSame('America/Lima', $result->voucherDate->getTimezone()->getName());
    }

    public function testHydrateParsesVoucherDateAfternoonUtc(): void
    {
        // 2025-06-20 15:00 UTC = 2025-06-20 10:00 Lima — same calendar date
        $voucher = $this->makeVoucher(['voucher_date' => '2025-06-20 15:00:00']);

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
    // AC-16: VoucherHydrator maps discountAmount and discountPercent per item
    // ──────────────────────────────────────────────────────────────────────────

    public function testHydrateItemMapsDiscountAmountAndDiscountPercent(): void
    {
        // AC-16: SaleItem with descuento=18.00 and descuento_porcentaje=10.00
        // → HydratedVoucherItem.discountAmount=18.00, discountPercent=10.00
        $item = new VoucherItem();
        $item->product_code       = 'PROD-DISC';
        $item->product_uom        = 'NIU';
        $item->quantity           = '1';
        $item->product_name       = 'Discounted Product';
        $item->unit_value         = '152.54';
        $item->taxed_amount       = '152.54';
        $item->tax_percentage     = '18.00';
        $item->tax_amount         = '27.46';
        $item->tax_type           = '10';
        $item->unit_price         = '180.00';
        $item->discount_amount    = '18.00';
        $item->discount_percentage = '10.00';

        $voucher = $this->makeVoucher(['voucher_items' => [$item]]);

        $result = $this->sut->hydrate($voucher);

        self::assertCount(1, $result->voucherItems);
        $hydratedItem = $result->voucherItems[0];

        // AC-16: discountAmount must be 18.00
        self::assertEqualsWithDelta(18.0, $hydratedItem->discountAmount, 0.001);
        // AC-16: discountPercent must be 10.00
        self::assertEqualsWithDelta(10.0, $hydratedItem->discountPercent ?? 0.0, 0.001);
    }

    public function testHydrateItemSetsDiscountAmountToZeroAndDiscountPercentToNullWhenNoDiscount(): void
    {
        // AC-16: item with no discount fields → discountAmount=0.0, discountPercent=null
        $item = new VoucherItem();
        $item->product_code       = 'PROD-NODISC';
        $item->product_uom        = 'NIU';
        $item->quantity           = '1';
        $item->product_name       = 'Full Price Product';
        $item->unit_value         = '84.75';
        $item->taxed_amount       = '84.75';
        $item->tax_percentage     = '18.00';
        $item->tax_amount         = '15.25';
        $item->tax_type           = '10';
        $item->unit_price         = '100.00';
        $item->discount_amount    = null;
        $item->discount_percentage = null;

        $voucher = $this->makeVoucher(['voucher_items' => [$item]]);

        $result = $this->sut->hydrate($voucher);

        $hydratedItem = $result->voucherItems[0];

        // No discount → discountAmount defaults to 0.0
        self::assertEqualsWithDelta(0.0, $hydratedItem->discountAmount, 0.001);
        // No discount → discountPercent is null
        self::assertNull($hydratedItem->discountPercent);
    }

    public function testHydrateVoucherDiscountTotalEqualsItemDiscountsPlusGlobalDiscount(): void
    {
        // AC-16: voucherDiscountTotal = sum(item discounts) + global discount amount
        // Item 1: discount_amount = 18.00
        // Item 2: discount_amount = 7.00
        // Global voucher discount: voucher_discount_amount = 5.00
        // Expected voucherDiscountTotal = 18 + 7 + 5 = 30.00
        $item1 = new VoucherItem();
        $item1->product_code       = 'PROD-A';
        $item1->product_uom        = 'NIU';
        $item1->quantity           = '1';
        $item1->product_name       = 'Product A';
        $item1->unit_value         = '152.54';
        $item1->taxed_amount       = '152.54';
        $item1->tax_percentage     = '18.00';
        $item1->tax_amount         = '27.46';
        $item1->tax_type           = '10';
        $item1->unit_price         = '180.00';
        $item1->discount_amount    = '18.00';
        $item1->discount_percentage = '10.00';

        $item2 = new VoucherItem();
        $item2->product_code       = 'PROD-B';
        $item2->product_uom        = 'NIU';
        $item2->quantity           = '1';
        $item2->product_name       = 'Product B';
        $item2->unit_value         = '59.32';
        $item2->taxed_amount       = '59.32';
        $item2->tax_percentage     = '18.00';
        $item2->tax_amount         = '10.68';
        $item2->tax_type           = '10';
        $item2->unit_price         = '70.00';
        $item2->discount_amount    = '7.00';
        $item2->discount_percentage = '10.00';

        $voucher = $this->makeVoucher([
            'voucher_items'           => [$item1, $item2],
            'voucher_discount_amount' => '5.00',
        ]);

        $result = $this->sut->hydrate($voucher);

        // voucherDiscountTotal = 18 + 7 + 5 = 30.00
        self::assertEqualsWithDelta(30.0, $result->voucherDiscountTotal, 0.001);
    }

    public function testHydrateVoucherDiscountTotalIsZeroWhenNoDiscounts(): void
    {
        // AC-16: no item discounts and no global discount → voucherDiscountTotal = 0.00
        $item = new VoucherItem();
        $item->product_code       = 'PROD-X';
        $item->product_uom        = 'NIU';
        $item->quantity           = '1';
        $item->product_name       = 'Product X';
        $item->unit_value         = '84.75';
        $item->taxed_amount       = '84.75';
        $item->tax_percentage     = '18.00';
        $item->tax_amount         = '15.25';
        $item->tax_type           = '10';
        $item->unit_price         = '100.00';
        $item->discount_amount    = null;
        $item->discount_percentage = null;

        $voucher = $this->makeVoucher([
            'voucher_items'           => [$item],
            'voucher_discount_amount' => '0.00',
        ]);

        $result = $this->sut->hydrate($voucher);

        self::assertEqualsWithDelta(0.0, $result->voucherDiscountTotal, 0.001);
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
