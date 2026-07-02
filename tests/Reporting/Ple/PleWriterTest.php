<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Ple;

use App\Billing\Shared\Enum\CreditNoteReason;
use App\Reporting\Ple\PleWriter;
use App\Reporting\Ple\SalesLedgerExporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PleWriterTest extends TestCase
{
    public function testFileNameUsesSunatIndicators(): void
    {
        self::assertSame('LE2012345678920260600140100001111.txt', PleWriter::fileName(
            '20123456789',
            '202606',
            '140100',
            true,
        ));
        self::assertSame('LE2012345678920260600080100001011.txt', PleWriter::fileName(
            '20123456789',
            '202606',
            '080100',
            false,
        ));
    }

    public function testCreditNoteAmountsAreNegativeInSalesLedger(): void
    {
        $fields = $this->salesRecordFields([
            'document_type' => '07',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'total' => '118.00',
        ]);

        self::assertSame('-100.00', $fields[13]);
        self::assertSame('-18.00', $fields[15]);
        self::assertSame('-118.00', $fields[24]);
        self::assertSame('1', $fields[35]);
    }

    public function testVoidedSalesLedgerRecordUsesStateTwoAndZeroAmounts(): void
    {
        $fields = $this->salesRecordFields([
            'void_ticket' => 'TICKET-1',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'total' => '118.00',
        ]);

        self::assertSame('0.00', $fields[13]);
        self::assertSame('0.00', $fields[15]);
        self::assertSame('0.00', $fields[24]);
        self::assertSame('2', $fields[35]);
    }

    public function testCreditNoteReasonCatalogIncludesOfficialCodes(): void
    {
        $options = CreditNoteReason::options();
        $codes = array_column($options, 'code');

        self::assertSame(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'], $codes);
        self::assertTrue(CreditNoteReason::ANULACION_OPERACION->annulsDocument());
        self::assertTrue(CreditNoteReason::ANULACION_ERROR_RUC->annulsDocument());
        self::assertTrue(CreditNoteReason::DEVOLUCION_TOTAL->annulsDocument());
        self::assertFalse(CreditNoteReason::DEVOLUCION_ITEM->annulsDocument());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<string>
     */
    private function salesRecordFields(array $overrides): array
    {
        $row = array_replace([
            'document_type' => '01',
            'series' => 'F001',
            'number' => '123',
            'issue_date' => '2026-06-12',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'total' => '118.00',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'operation_type' => '0101',
            'void_ticket' => null,
            'void_correlation' => null,
            'customer_doc' => '20123456789',
            'customer_name' => 'Cliente SAC',
            'modified_doc_type' => null,
            'modified_series' => null,
            'modified_number' => null,
            'modified_issue_date' => null,
        ], $overrides);

        $reflection = new ReflectionClass(SalesLedgerExporter::class);
        $exporter = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildRecord');
        $line = (string) $method->invoke($exporter, '20260600', 1, $row);

        return explode('|', rtrim($line, "\r\n|"));
    }
}
