<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fiscal;

use App\Integration\Fiscal\DocumentInput;
use App\Integration\Fiscal\InvalidDocumentInput;
use PHPUnit\Framework\TestCase;

final class DocumentInputTest extends TestCase
{
    /** @return array<string, mixed> */
    private function validBody(): array
    {
        return [
            'external_id' => '0192abcd-1234-7890-abcd-ef0123456789',
            'document_type' => 'invoice',
            'series' => 'F001',
            'number' => 1234,
            'currency' => 'PEN',
            'subtotal_minor' => 8390,
            'tax_minor' => 1510,
            'total_minor' => 9900,
            'customer' => ['document_type' => 'RUC', 'document_number' => '20123456789', 'name' => 'Empresa S.A.C.'],
            'line_description' => 'Hosting Plan Plus - anual',
        ];
    }

    public function testParsesAValidInvoiceBody(): void
    {
        $input = DocumentInput::fromArray($this->validBody());

        self::assertSame('0192abcd-1234-7890-abcd-ef0123456789', $input->externalId);
        self::assertTrue($input->isInvoice());
        self::assertSame('01', $input->sunatDocumentType());
        self::assertSame('F001', $input->series);
        self::assertSame(1234, $input->number);
        self::assertSame(9900, $input->totalMinor);
        self::assertSame('RUC', $input->customer->docType);
    }

    public function testReceiptMapsToSunat03(): void
    {
        $body = $this->validBody();
        $body['document_type'] = 'receipt';
        $body['customer'] = ['name' => 'Consumidor Final'];

        $input = DocumentInput::fromArray($body);

        self::assertFalse($input->isInvoice());
        self::assertSame('03', $input->sunatDocumentType());
        self::assertNull($input->customer->docType);
    }

    public function testCollectsEveryValidationError(): void
    {
        try {
            DocumentInput::fromArray([
                'external_id' => 'nope',
                'document_type' => 'credit_note',
                'series' => 'bad',
                'number' => 0,
                'currency' => 'peso',
                'subtotal_minor' => -1,
                'tax_minor' => 'x',
                'total_minor' => 10,
                'customer' => ['document_type' => 'CE', 'name' => ''],
            ]);
            self::fail('expected InvalidDocumentInput');
        } catch (InvalidDocumentInput $e) {
            $fields = array_column($e->errors, 'field');
            self::assertContains('external_id', $fields);
            self::assertContains('document_type', $fields);
            self::assertContains('series', $fields);
            self::assertContains('number', $fields);
            self::assertContains('currency', $fields);
            self::assertContains('subtotal_minor', $fields);
            self::assertContains('tax_minor', $fields);
            self::assertContains('customer.name', $fields);
            self::assertContains('customer.document_type', $fields);
        }
    }

    public function testExternalIdIsLowercased(): void
    {
        $body = $this->validBody();
        $body['external_id'] = 'ABCDEF01-1234-7890-ABCD-EF0123456789';

        self::assertSame('abcdef01-1234-7890-abcd-ef0123456789', DocumentInput::fromArray($body)->externalId);
    }
}
