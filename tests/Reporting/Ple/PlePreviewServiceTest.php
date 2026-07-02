<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Ple;

use App\Reporting\Ple\PlePreviewService;
use App\Reporting\Ple\SalesLedgerExporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlePreviewServiceTest extends TestCase
{
    public function testUnavailableBookPlaceholderStructure(): void
    {
        $reflection = new ReflectionClass(PlePreviewService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('unavailableBook');

        $book = $method->invoke($service, 'diary', 'Libro Diario', 'LE05.2');

        self::assertSame('unavailable', $book['status']);
        self::assertSame('Sin datos aún', $book['statusLabel']);
        self::assertSame('Libro Diario', $book['label']);
        self::assertSame([], $book['rows']);
    }

    public function testSalesPreviewRowMapsCreditNoteAsNegativeAmounts(): void
    {
        $row = $this->salesPreviewRow([
            'document_type' => '07',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'total' => '118.00',
        ]);

        self::assertSame(-100.0, $row['taxable']);
        self::assertSame(-18.0, $row['igv']);
        self::assertSame(-118.0, $row['total']);
        self::assertSame('Activo', $row['stateLabel']);
    }

    public function testSalesPreviewRowMarksVoidedDocumentsAsAnulado(): void
    {
        $row = $this->salesPreviewRow([
            'void_ticket' => 'TICKET-1',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'total' => '118.00',
        ]);

        self::assertSame(0.0, $row['taxable']);
        self::assertSame(0.0, $row['igv']);
        self::assertSame(0.0, $row['total']);
        self::assertSame('Anulado', $row['stateLabel']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function salesPreviewRow(array $overrides): array
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
        $resolveAmounts = $reflection->getMethod('resolveAmounts');
        $toPreviewRow = $reflection->getMethod('toPreviewRow');

        $amounts = $resolveAmounts->invoke($exporter, $row);

        return $toPreviewRow->invoke($exporter, 1, $row, $amounts);
    }
}
