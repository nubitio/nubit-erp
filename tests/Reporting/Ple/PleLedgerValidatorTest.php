<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Ple;

use App\Reporting\Ple\PleLedgerValidator;
use PHPUnit\Framework\TestCase;

final class PleLedgerValidatorTest extends TestCase
{
    private PleLedgerValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PleLedgerValidator();
    }

    public function testSalesBookAcceptsValidRow(): void
    {
        $issues = $this->validator->validateSalesBook([
            'status' => 'ready',
            'rows' => [[
                'index' => 1,
                'issueDate' => '12/06/2026',
                'documentType' => '03',
                'series' => 'B001',
                'number' => '123',
                'counterpartyDoc' => '12345678',
                'taxable' => 100.0,
                'igv' => 18.0,
                'total' => 118.0,
                'state' => '1',
            ]],
        ], '202606');

        self::assertSame([], $issues);
    }

    public function testSalesBookFlagsInvoiceWithoutRuc(): void
    {
        $issues = $this->validator->validateSalesBook([
            'status' => 'ready',
            'rows' => [[
                'index' => 2,
                'issueDate' => '12/06/2026',
                'documentType' => '01',
                'series' => 'F001',
                'number' => '45',
                'counterpartyDoc' => '12345678',
                'taxable' => 100.0,
                'igv' => 18.0,
                'total' => 118.0,
                'state' => '1',
            ]],
        ], '202606');

        self::assertCount(1, $issues);
        self::assertSame('INVALID_RUC', $issues[0]['code']);
    }

    public function testPurchasesBookFlagsMissingSupplierDocumentType(): void
    {
        $issues = $this->validator->validatePurchasesBook([
            'status' => 'ready',
            'rows' => [[
                'index' => 1,
                'issueDate' => '05/06/2026',
                'documentType' => '00',
                'series' => 'E001',
                'number' => '88',
                'taxable' => 50.0,
                'igv' => 9.0,
                'total' => 59.0,
            ]],
        ], '202606');

        self::assertNotEmpty($issues);
        self::assertSame('MISSING_SUPPLIER_DOC_TYPE', $issues[0]['code']);
    }

    public function testEmptyBookHasNoIssues(): void
    {
        $issues = $this->validator->validateSalesBook([
            'status' => 'empty',
            'rows' => [],
        ], '202606');

        self::assertSame([], $issues);
    }
}