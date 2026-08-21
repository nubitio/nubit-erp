<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Ple;

use App\Reporting\Ple\AccountingBookExporter;
use App\Reporting\Ple\JournalPleFormatter;
use PHPUnit\Framework\TestCase;

final class AccountingBookExporterTest extends TestCase
{
    public function testBookCodesMatchSunatDiaryAndMayor(): void
    {
        self::assertSame('050100', AccountingBookExporter::BOOK_DIARY);
        self::assertSame('060100', AccountingBookExporter::BOOK_MAYOR);
    }

    public function testDiaryRecordHasTwentyOneSunatFields(): void
    {
        $fields = $this->recordFields([
            'entry_no' => 'A2026-000046',
            'line_no' => 2,
            'account_code' => '70111',
            'debit' => '0.00',
            'credit' => '100.00',
            'source' => 'sale',
            'sale_doc_type' => '03',
            'sale_series' => 'B001',
            'sale_number' => '11',
        ]);

        self::assertCount(21, $fields);
        self::assertSame('20260800', $fields[0]);
        self::assertSame('M2026000046', $fields[1]);
        self::assertSame('M2', $fields[2]);
        self::assertSame('70111', $fields[3]);
        self::assertSame('PEN', $fields[6]);
        self::assertSame('6', $fields[7]);
        self::assertSame('20123456789', $fields[8]);
        self::assertSame('03', $fields[9]);
        self::assertSame('B001', $fields[10]);
        self::assertSame('11', $fields[11]);
        self::assertSame('15/08/2026', $fields[12]);
        self::assertSame('15/08/2026', $fields[14]);
        self::assertSame('Venta B001-11', $fields[15]);
        self::assertSame('Venta neta', $fields[16]);
        self::assertSame('0.00', $fields[17]);
        self::assertSame('100.00', $fields[18]);
        self::assertSame('1', $fields[20]);
    }

    public function testCashMovementOmitsIssuerAndVoucher(): void
    {
        $fields = $this->recordFields([
            'source' => 'cash_movement',
            'description' => 'Retiro de caja',
            'memo' => '',
            'account_code' => '101',
            'debit' => '0.00',
            'credit' => '50.00',
            'sale_doc_type' => '03',
            'sale_series' => 'B001',
            'sale_number' => '11',
        ]);

        self::assertSame('', $fields[7]);
        self::assertSame('', $fields[8]);
        self::assertSame('', $fields[9]);
        self::assertSame('', $fields[10]);
        self::assertSame('', $fields[11]);
        self::assertSame('Retiro de caja', $fields[15]);
    }

    public function testCuoIsStableForTheSameEntry(): void
    {
        $first = $this->recordFields(['entry_no' => 'A2026-000007', 'line_no' => 1]);
        $second = $this->recordFields(['entry_no' => 'A2026-000007', 'line_no' => 3]);

        self::assertSame('M2026000007', $first[1]);
        self::assertSame($first[1], $second[1]);
        self::assertSame('M1', $first[2]);
        self::assertSame('M3', $second[2]);
    }

    public function testPreviewRowMapsDebitCreditAndAccount(): void
    {
        $row = $this->previewRow([
            'entry_no' => 'A2026-000001',
            'account_code' => '101',
            'account_name' => 'Caja',
            'debit' => '118.00',
            'credit' => '0.00',
            'source' => 'sale',
            'sale_doc_type' => '01',
            'sale_series' => 'F001',
            'sale_number' => '8',
        ]);

        self::assertSame('M2026000001', $row['cuo']);
        self::assertSame('M1', $row['correlative']);
        self::assertSame('101', $row['accountCode']);
        self::assertSame('Caja', $row['accountName']);
        self::assertSame(118.0, $row['debit']);
        self::assertSame(0.0, $row['credit']);
        self::assertSame('01', $row['documentType']);
        self::assertSame('F001', $row['series']);
        self::assertSame('8', $row['number']);
        self::assertSame('Activo', $row['stateLabel']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<string>
     */
    private function recordFields(array $overrides): array
    {
        $line = JournalPleFormatter::buildRecord('20260800', $this->journalRow($overrides), '20123456789');

        return explode('|', rtrim($line, "\r\n|"));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function previewRow(array $overrides): array
    {
        $row = $this->journalRow($overrides);

        return JournalPleFormatter::toPreviewRow(1, $row, (float) $row['debit'], (float) $row['credit']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function journalRow(array $overrides): array
    {
        return array_replace([
            'entry_no' => 'A2026-000001',
            'entry_date' => '2026-08-15',
            'description' => 'Venta B001-11',
            'source' => 'sale',
            'currency' => 'PEN',
            'line_no' => 1,
            'debit' => '118.00',
            'credit' => '0.00',
            'memo' => 'Venta neta',
            'account_code' => '101',
            'account_name' => 'Caja',
            'sale_series' => 'B001',
            'sale_number' => '11',
            'sale_doc_type' => '03',
            'supplier_doc_type' => null,
            'supplier_doc_series' => null,
            'supplier_doc_number' => null,
            'supplier_ruc' => null,
        ], $overrides);
    }
}
