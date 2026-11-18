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

    /** @return array<string, mixed> */
    private function bodyWithLines(): array
    {
        $body = [
            'external_id' => '0192abcd-1234-7890-abcd-ef0123456789',
            'document_type' => 'invoice',
            'series' => 'F001',
            'number' => 7,
            'currency' => 'PEN',
            'subtotal_minor' => 21695,
            'tax_minor' => 3905,
            'total_minor' => 25600,
            'customer' => ['document_type' => 'RUC', 'document_number' => '20123456789', 'name' => 'Empresa S.A.C.'],
            'line_description' => 'Illari',
            'lines' => [
                [
                    'description' => 'Illari Profesional',
                    'quantity' => 1,
                    'subtotal_minor' => 16864,
                    'tax_minor' => 3036,
                    'total_minor' => 19900,
                ],
                [
                    'description' => 'Alumno adicional',
                    'quantity' => 3,
                    'subtotal_minor' => 1017,
                    'tax_minor' => 183,
                    'total_minor' => 1200,
                ],
                [
                    'description' => 'Onboarding',
                    'quantity' => 1,
                    'subtotal_minor' => 3814,
                    'tax_minor' => 686,
                    'total_minor' => 4500,
                ],
            ],
        ];
        $body['subtotal_minor'] = 16864 + 1017 + 3814;
        $body['tax_minor'] = 3036 + 183 + 686;
        $body['total_minor'] = 19900 + 1200 + 4500;

        return $body;
    }

    public function testBuildsOneItemPerLine(): void
    {
        $voucher = new SyntheticVoucherFactory()->fromInput(DocumentInput::fromArray($this->bodyWithLines()), 'c');

        self::assertCount(3, $voucher->voucher_items);
        self::assertSame('Alumno adicional', $voucher->voucher_items[1]->product_name);
        self::assertSame('3', $voucher->voucher_items[1]->quantity);
        self::assertSame('3.390000', $voucher->voucher_items[1]->unit_value);
        self::assertSame('12.00', $voucher->voucher_items[1]->total);
        self::assertSame('256.00', $voucher->voucher_sale_amount);
    }

    public function testLinesMustSumToTheDocumentTotals(): void
    {
        $body = $this->bodyWithLines();
        $body['total_minor'] = (int) $body['total_minor'] + 1;

        $this->expectException(\App\Integration\Fiscal\InvalidDocumentInput::class);
        DocumentInput::fromArray($body);
    }

    public function testLineTotalMustEqualNetPlusTax(): void
    {
        $body = $this->bodyWithLines();
        /** @var list<array<string, int>> $lines */
        $lines = $body['lines'];
        $lines[0]['total_minor'] += 1;
        $body['lines'] = $lines;
        $body['total_minor'] = (int) $body['total_minor'] + 1;

        $this->expectException(\App\Integration\Fiscal\InvalidDocumentInput::class);
        DocumentInput::fromArray($body);
    }
}
