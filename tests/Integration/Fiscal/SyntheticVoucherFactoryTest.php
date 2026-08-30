<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fiscal;

use App\Integration\Fiscal\DocumentInput;
use App\Integration\Fiscal\SyntheticVoucherFactory;
use PHPUnit\Framework\TestCase;

final class SyntheticVoucherFactoryTest extends TestCase
{
    private function input(string $type = 'invoice'): DocumentInput
    {
        return DocumentInput::fromArray([
            'external_id' => '0192abcd-1234-7890-abcd-ef0123456789',
            'document_type' => $type,
            'series' => $type === 'invoice' ? 'F001' : 'B001',
            'number' => 42,
            'currency' => 'PEN',
            'subtotal_minor' => 8390,
            'tax_minor' => 1510,
            'total_minor' => 9900,
            'customer' => $type === 'invoice'
                ? ['document_type' => 'RUC', 'document_number' => '20123456789', 'name' => 'Empresa S.A.C.']
                : ['name' => 'Consumidor Final'],
            'line_description' => 'Hosting Plan Plus',
            'order_reference' => 'ord_abc123',
        ]);
    }

    public function testBuildsAOneLineTaxedVoucher(): void
    {
        $factory = new SyntheticVoucherFactory();
        $voucher = $factory->fromInput($this->input(), 'company-uuid', new \DateTimeImmutable('2026-08-30T10:00:00Z'));

        self::assertNull($voucher->sale_id);
        self::assertSame('company-uuid', $voucher->company_id);
        self::assertSame('01', $voucher->voucher_type);
        self::assertSame('F001', $voucher->voucher_series);
        self::assertSame('42', $voucher->voucher_number);
        self::assertSame('2026-08-30', $voucher->voucher_date);
        self::assertSame('83.90', $voucher->voucher_taxed_amount);
        self::assertSame('15.10', $voucher->voucher_tax_amount);
        self::assertSame('99.00', $voucher->voucher_sale_amount);
        self::assertSame('18.00', $voucher->voucher_tax_percentage);
        self::assertSame('0101', $voucher->voucher_operation_type);
        self::assertSame('Ref: ord_abc123', $voucher->voucher_note);
        self::assertCount(1, $voucher->voucher_items);

        $item = $voucher->voucher_items[0];
        self::assertSame('1', $item->quantity);
        self::assertSame('83.90', $item->unit_value);
        self::assertSame('99.00', $item->unit_price);
        self::assertSame('15.10', $item->tax_amount);
        self::assertSame('18.00', $item->tax_percentage);
        self::assertSame('10', $item->tax_type);
        self::assertSame('Hosting Plan Plus', $item->product_name);
    }

    public function testCustomerDocumentTypeMapsToSunatCatalog06(): void
    {
        $factory = new SyntheticVoucherFactory();

        self::assertSame('6', $factory->fromInput($this->input('invoice'), 'c')->customer_doc_type);
        self::assertSame('0', $factory->fromInput($this->input('receipt'), 'c')->customer_doc_type);
    }
}
