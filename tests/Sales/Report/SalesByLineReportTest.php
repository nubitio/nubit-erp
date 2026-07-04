<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report;

use App\Sales\Report\SalesByLineReport;
use App\Settings\Service\SunatStateService;
use Nubit\Platform\Export\XlsColumn;
use Nubit\Platform\Export\XlsColumnSpec;
use Nubit\Platform\Export\XlsExporter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\TestCase;

final class SalesByLineReportTest extends TestCase
{
    public function testColumnsDeclareNumericExportTypesForSpreadsheetFormulas(): void
    {
        $report = new SalesByLineReport($this->createStub(SunatStateService::class));

        $columns = $report->columns();

        self::assertNumericColumn($columns['quantity'], 'Cantidad', '#,##0.###', XlsColumn::SUMMARY_SUM);
        self::assertNumericColumn($columns['unit_price'], 'Precio unit.', '#,##0.0000', XlsColumn::SUMMARY_NONE);
        self::assertNumericColumn($columns['discount'], 'Descuento', '#,##0.00', XlsColumn::SUMMARY_SUM);
        self::assertNumericColumn($columns['total'], 'Total', '#,##0.00', XlsColumn::SUMMARY_SUM);
    }

    public function testExporterWritesSalesLineTotalsAsNumericCells(): void
    {
        $report = new SalesByLineReport($this->createStub(SunatStateService::class));

        $spreadsheet = (new XlsExporter())->makeSpreadsheet([
            [
                'sale_date' => '2026-06-01',
                'sale_time' => '10:00:00',
                'document' => 'F001-1',
                'voucher_type' => 'Factura',
                'customer_name' => 'Cliente',
                'customer_ruc' => '20123456789',
                'payment_method' => 'Efectivo',
                'product_code' => 'SKU-1',
                'base_product' => 'Producto',
                'line_description' => 'Producto',
                'category' => 'General',
                'quantity' => '1.100',
                'unit' => 'KGM',
                'unit_price' => '14.0000',
                'discount' => '0.00',
                'total' => '15.40',
                'status' => 'Aceptado',
            ],
        ], $report->columns());

        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('L2')->getDataType());
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('P2')->getDataType());
        self::assertSame(15.4, $sheet->getCell('P2')->getValue());
        self::assertSame('=SUM(P2:P2)', $sheet->getCell('P3')->getValue());
    }

    private static function assertNumericColumn(
        mixed $column,
        string $label,
        string $format,
        string $summary,
    ): void {
        self::assertInstanceOf(XlsColumnSpec::class, $column);
        self::assertSame($label, $column->label);
        self::assertSame(XlsColumn::TYPE_NUMBER, $column->type);
        self::assertSame($format, $column->presentation->format);
        self::assertSame($summary, $column->summary->type);
    }
}
