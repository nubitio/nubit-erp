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

    public function testJournalBookAcceptsBalancedEntry(): void
    {
        $issues = $this->validator->validateJournalBook([
            'status' => 'ready',
            'rows' => [
                $this->journalRow(1, 'M2026000001', '101', 118.0, 0.0),
                $this->journalRow(2, 'M2026000001', '70111', 0.0, 100.0),
                $this->journalRow(3, 'M2026000001', '40111', 0.0, 18.0),
            ],
        ], '202608');

        self::assertSame([], $issues);
    }

    public function testJournalBookFlagsUnbalancedEntry(): void
    {
        $issues = $this->validator->validateJournalBook([
            'status' => 'ready',
            'rows' => [
                $this->journalRow(1, 'M2026000002', '101', 118.0, 0.0),
                $this->journalRow(2, 'M2026000002', '70111', 0.0, 100.0),
            ],
        ], '202608');

        self::assertNotEmpty($issues);
        self::assertSame('UNBALANCED_ENTRY', $issues[0]['code']);
    }

    public function testJournalBookFlagsMissingAccount(): void
    {
        $issues = $this->validator->validateJournalBook([
            'status' => 'ready',
            'rows' => [$this->journalRow(4, 'M2026000003', '', 10.0, 0.0)],
        ], '202608');

        $codes = array_column($issues, 'code');
        self::assertContains('MISSING_ACCOUNT', $codes);
    }

    /**
     * @return array<string, mixed>
     */
    private function journalRow(int $index, string $cuo, string $account, float $debit, float $credit): array
    {
        return [
            'index' => $index,
            'issueDate' => '15/08/2026',
            'cuo' => $cuo,
            'correlative' => 'M' . $index,
            'accountCode' => $account,
            'debit' => $debit,
            'credit' => $credit,
        ];
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
