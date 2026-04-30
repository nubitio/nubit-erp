<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Dto;

use App\Billing\Invoice\Dto\VoucherHeaderDto;
use App\Billing\Service\InvoiceAmounts;
use PHPUnit\Framework\TestCase;

final class VoucherHeaderDtoTest extends TestCase
{
    // ── Constructor defaults ──────────────────────────────────────────────────

    public function testAllFieldsDefaultToNull(): void
    {
        $dto = new VoucherHeaderDto();

        self::assertNull($dto->saleId);
        self::assertNull($dto->customerDocType);
        self::assertNull($dto->customerName);
        self::assertNull($dto->voucherType);
        self::assertNull($dto->voucherSeries);
        self::assertNull($dto->voucherNumber);
        self::assertNull($dto->voucherPaymentMethod);
        self::assertNull($dto->voucherCurrency);
        self::assertNull($dto->voucherTaxedAmount);
        self::assertNull($dto->voucherTaxAmount);
        self::assertNull($dto->voucherSaleAmount);
        self::assertNull($dto->voucherStatus);
    }

    // ── fromArray ─────────────────────────────────────────────────────────────

    public function testFromArrayMapsAllPresentKeys(): void
    {
        $row = [
            'sale_id'               => 42,
            'customer_doc_type'     => '6',
            'customer_doc_number'   => '20123456789',
            'customer_name'         => 'Empresa SAC',
            'customer_address'      => 'Av. Lima 1',
            'customer_email'        => 'test@example.com',
            'customer_phone'        => '01-234-5678',
            'company_id'            => '20999888777',
            'company_name'          => 'Mi Empresa',
            'company_address'       => 'Jr. Miraflores 100',
            'company_phone'         => '01-111-2222',
            'branch_address'        => 'Jr. Miraflores 100 Of. 2',
            'branch_phone'          => '01-111-2223',
            'voucher_type'          => '01',
            'voucher_series'        => 'F001',
            'voucher_number'        => '00000123',
            'voucher_payment_method'=> '1',
            'voucher_date'          => '2026-04-30',
            'voucher_currency'      => 'PEN',
            'voucher_exchange_rate' => '1.000',
            'voucher_taxed_amount'  => '100.00',
            'voucher_tax_amount'    => '18.00',
            'voucher_total_taxes'   => '18.00',
            'voucher_sale_value'    => '100.00',
            'voucher_sale_amount'   => '118.00',
            'voucher_status'        => '0',
        ];

        $dto = VoucherHeaderDto::fromArray($row);

        self::assertSame('42', $dto->saleId);
        self::assertSame('6', $dto->customerDocType);
        self::assertSame('20123456789', $dto->customerDocNumber);
        self::assertSame('Empresa SAC', $dto->customerName);
        self::assertSame('test@example.com', $dto->customerEmail);
        self::assertSame('01', $dto->voucherType);
        self::assertSame('F001', $dto->voucherSeries);
        self::assertSame('00000123', $dto->voucherNumber);
        self::assertSame(1, $dto->voucherPaymentMethod);
        self::assertSame('PEN', $dto->voucherCurrency);
        self::assertSame('100.00', $dto->voucherTaxedAmount);
        self::assertSame('18.00', $dto->voucherTaxAmount);
        self::assertSame('118.00', $dto->voucherSaleAmount);
        self::assertSame(0, $dto->voucherStatus);
    }

    public function testFromArrayReturnsNullForMissingKeys(): void
    {
        $dto = VoucherHeaderDto::fromArray([]);

        self::assertNull($dto->saleId);
        self::assertNull($dto->customerDocType);
        self::assertNull($dto->voucherType);
        self::assertNull($dto->voucherStatus);
    }

    public function testFromArrayHandlesStringOrNullFields(): void
    {
        $dto = VoucherHeaderDto::fromArray([
            'voucher_discount_amount'     => '5.00',
            'voucher_discount_percentage' => '0.05',
            'voucher_operation_type'      => '0101',
        ]);

        self::assertSame('5.00', $dto->voucherDiscountAmount);
        self::assertSame('0.05', $dto->voucherDiscountPercentage);
        self::assertSame('0101', $dto->voucherOperationType);
    }

    // ── applyAmounts ──────────────────────────────────────────────────────────

    public function testApplyAmountsSetsAllAmountFields(): void
    {
        $amounts = new InvoiceAmounts();
        $amounts->setTaxedAmount('100.00');
        $amounts->setTax('18.00');
        $amounts->setSubtotal('100.00');
        $amounts->setTotal('118.00');
        $amounts->setDiscountAmount('0.00');
        $amounts->setChargesAmount('0.00');

        $dto = new VoucherHeaderDto();
        $dto->applyAmounts($amounts);

        self::assertSame('100.00', $dto->voucherTaxedAmount);
        self::assertSame('18.00', $dto->voucherTaxAmount);
        self::assertSame('18.00', $dto->voucherTotalTaxes);
        self::assertSame('0.00', $dto->voucherConsumptionSurchargeAmount);
        self::assertSame('0.00', $dto->voucherDiscountAmount);
        self::assertSame('100.00', $dto->voucherSaleValue);
        self::assertSame('118.00', $dto->voucherSaleAmount);
    }

    // ── toVoucher ─────────────────────────────────────────────────────────────

    public function testToVoucherMapsAllFieldsToVoucherObject(): void
    {
        $dto = new VoucherHeaderDto(
            saleId: 'sale-1',
            customerDocType: '6',
            customerDocNumber: '20123456789',
            customerName: 'Cliente SA',
            voucherType: '01',
            voucherSeries: 'F001',
            voucherNumber: '00000001',
            voucherCurrency: 'PEN',
            voucherSaleAmount: '118.00',
        );

        $voucher = $dto->toVoucher([]);

        self::assertSame('sale-1', $voucher->sale_id);
        self::assertSame('6', $voucher->customer_doc_type);
        self::assertSame('20123456789', $voucher->customer_doc_number);
        self::assertSame('Cliente SA', $voucher->customer_name);
        self::assertSame('01', $voucher->voucher_type);
        self::assertSame('F001', $voucher->voucher_series);
        self::assertSame('PEN', $voucher->voucher_currency);
        self::assertSame('118.00', $voucher->voucher_sale_amount);
        self::assertSame([], $voucher->voucher_items);
    }
}
