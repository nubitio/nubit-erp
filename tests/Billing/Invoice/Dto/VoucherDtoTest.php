<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Dto;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Domain\VoucherItem;
use App\Billing\Invoice\Dto\VoucherHeaderDto;
use App\Billing\Invoice\Dto\VoucherLineDto;
use App\Billing\Service\InvoiceAmounts;
use App\Billing\Service\InvoiceLineAmounts;
use PHPUnit\Framework\TestCase;

final class VoucherDtoTest extends TestCase
{
    // ── VoucherHeaderDto ──────────────────────────────────────────────────────

    public function testFromArrayMapsKnownKeys(): void
    {
        $row = [
            'sale_id'           => 'sale-001',
            'customer_doc_type' => '6',
            'customer_name'     => 'Empresa ABC',
            'voucher_type'      => '01',
            'voucher_series'    => 'F001',
            'voucher_number'    => '42',
        ];

        $dto = VoucherHeaderDto::fromArray($row);

        self::assertSame('sale-001', $dto->saleId);
        self::assertSame('6', $dto->customerDocType);
        self::assertSame('Empresa ABC', $dto->customerName);
        self::assertSame('01', $dto->voucherType);
        self::assertSame('F001', $dto->voucherSeries);
        self::assertSame('42', $dto->voucherNumber);
    }

    public function testFromArrayReturnsNullForMissingKeys(): void
    {
        $dto = VoucherHeaderDto::fromArray([]);

        self::assertNull($dto->saleId);
        self::assertNull($dto->customerName);
        self::assertNull($dto->voucherType);
    }

    public function testApplyAmountsUpdatesFields(): void
    {
        $dto = new VoucherHeaderDto();
        $amounts = (new InvoiceAmounts())
            ->setTaxedAmount('84.75')
            ->setTax('15.25')
            ->setChargesAmount('0.00')
            ->setDiscountAmount('5.00')
            ->setSubtotal('100.00')
            ->setTotal('115.25');

        $dto->applyAmounts($amounts);

        self::assertSame('84.75', $dto->voucherTaxedAmount);
        self::assertSame('15.25', $dto->voucherTaxAmount);
        self::assertSame('15.25', $dto->voucherTotalTaxes);
        self::assertSame('0.00', $dto->voucherConsumptionSurchargeAmount);
        self::assertSame('5.00', $dto->voucherDiscountAmount);
        self::assertSame('100.00', $dto->voucherSaleValue);
        self::assertSame('115.25', $dto->voucherSaleAmount);
    }

    public function testToVoucherProducesVoucherWithItems(): void
    {
        $dto = VoucherHeaderDto::fromArray([
            'sale_id'        => 'sale-42',
            'voucher_type'   => '01',
            'voucher_series' => 'F001',
            'voucher_number' => '100',
        ]);

        $item = new VoucherItem(
            product_name: 'Producto A',
            quantity: '2',
            unit_price: '50.00',
        );

        $voucher = $dto->toVoucher([$item]);

        self::assertInstanceOf(Voucher::class, $voucher);
        self::assertSame('sale-42', $voucher->sale_id);
        self::assertSame('01', $voucher->voucher_type);
        self::assertSame('F001', $voucher->voucher_series);
        self::assertCount(1, $voucher->voucher_items);
        self::assertSame($item, $voucher->voucher_items[0]);
    }

    // ── VoucherLineDto ────────────────────────────────────────────────────────

    public function testVoucherLineDtoFromArray(): void
    {
        $row = [
            'product_id'   => 'prod-001',
            'product_name' => 'Laptop',
            'quantity'     => '1',
            'unit_price'   => '2500.00',
            'tax_amount'   => '450.00',
            'total'        => '2950.00',
        ];

        $dto = VoucherLineDto::fromArray($row);

        self::assertSame('prod-001', $dto->productId);
        self::assertSame('Laptop', $dto->productName);
        self::assertSame('1', $dto->quantity);
        self::assertSame('2500.00', $dto->unitPrice);
        self::assertSame('450.00', $dto->taxAmount);
        self::assertSame('2950.00', $dto->total);
    }

    public function testVoucherLineDtoFromArrayNullForMissing(): void
    {
        $dto = VoucherLineDto::fromArray([]);

        self::assertNull($dto->productId);
        self::assertNull($dto->productName);
        self::assertNull($dto->total);
    }

    public function testVoucherLineDtoApplyAmounts(): void
    {
        $dto = new VoucherLineDto(productName: 'Producto B', quantity: '2');
        $amounts = new InvoiceLineAmounts(
            quantity: '2',
            unitPrice: '59.00',
            unitValue: '50.00',
            baseAmount: '100.00',
            discountAmount: '0.00',
            taxableAmount: '100.00',
            chargesAmount: '0.00',
            taxAmount: '18.00',
            totalAmount: '118.00',
        );

        $dto->applyAmounts($amounts);

        self::assertSame('50.00', $dto->unitValue);
        self::assertSame('59.00', $dto->unitPrice);
        self::assertSame('0.00', $dto->discountAmount);
        self::assertSame('100.00', $dto->taxedAmount);
        self::assertSame('18.00', $dto->taxAmount);
        self::assertSame('118.00', $dto->total);
    }
}
