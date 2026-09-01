<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report;

use App\Sales\Report\AbstractSaleReport;
use App\Sales\Report\AccountingReport;
use App\Sales\Report\CreditNotesReport;
use App\Sales\Report\DailySummaryReport;
use App\Sales\Report\DetailedSalesReport;
use App\Sales\Report\PaymentMethodBreakdownReport;
use App\Sales\Report\PaymentMethodDailyReport;
use App\Sales\Report\SaleByProductReport;
use App\Sales\Report\SalesByLineReport;
use App\Sales\Report\VoidedSalesReport;
use App\Settings\Service\SunatStateService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Export\XlsColumn;
use Nubit\Platform\Export\XlsColumnSpec;
use Nubit\Platform\Export\XlsExporter;
use Nubit\Platform\Report\ExportableReportInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesReportSpreadsheetColumnsTest extends TestCase
{
    /**
     * @param array<string, array{0: string, 1: string, 2: string}> $expected
     */
    #[DataProvider('reportColumnsProvider')]
    public function testReportsDeclareNumericExportColumns(string $reportClass, array $expected): void
    {
        $columns = $this->newReport($reportClass)->columns();

        foreach ($expected as $field => [$label, $format, $summary]) {
            self::assertArrayHasKey($field, $columns);
            self::assertNumericColumn($columns[$field], $label, $format, $summary);
        }
    }

    /**
     * @param array<string, string> $numericValues
     * @param list<string> $summedFields
     */
    #[DataProvider('reportExporterProvider')]
    public function testExporterWritesNumericReportColumnsAsNumbers(
        string $reportClass,
        array $numericValues,
        array $summedFields,
    ): void {
        $report = $this->newReport($reportClass);
        $columns = $report->columns();
        $row = [];
        foreach (array_keys($columns) as $field) {
            $row[$field] = $numericValues[$field] ?? 'texto';
        }

        $spreadsheet = new XlsExporter()->makeSpreadsheet([$row], $columns);
        $sheet = $spreadsheet->getActiveSheet();
        $fieldIndexes = array_flip(array_keys($columns));

        foreach ($numericValues as $field => $value) {
            $columnIndex = $fieldIndexes[$field] + 1;
            $cell = $sheet->getCell([$columnIndex, 2]);
            self::assertSame(DataType::TYPE_NUMERIC, $cell->getDataType(), $field);
            self::assertSame((float) $value, (float) $cell->getValue(), $field);
        }

        foreach ($summedFields as $field) {
            $columnIndex = $fieldIndexes[$field] + 1;
            $columnLetter = $sheet->getCell([$columnIndex, 1])->getColumn();
            self::assertSame(
                sprintf('=SUM(%s2:%s2)', $columnLetter, $columnLetter),
                $sheet->getCell([$columnIndex, 3])->getValue(),
            );
        }
    }

    /**
     * @return iterable<string, array{0: class-string<ExportableReportInterface>, 1: array<string, array{0: string, 1: string, 2: string}>}>
     */
    public static function reportColumnsProvider(): iterable
    {
        yield 'sales-by-line' => [
            SalesByLineReport::class,
            [
                'quantity' => ['Cantidad', '#,##0.###', XlsColumn::SUMMARY_SUM],
                'unit_price' => ['Precio unit.', '#,##0.0000', XlsColumn::SUMMARY_NONE],
                'discount' => ['Descuento', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'detailed-sales' => [
            DetailedSalesReport::class,
            [
                'subtotal' => ['Subtotal', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'tax' => ['IGV', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'discount' => ['Descuento', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'sale-by-product' => [
            SaleByProductReport::class,
            [
                'quantity' => ['Cantidad', '#,##0.###', XlsColumn::SUMMARY_SUM],
                'average_unit_price' => ['Precio unit. prom.', '#,##0.00', XlsColumn::SUMMARY_NONE],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'accounting-report' => [
            AccountingReport::class,
            [
                'taxable_amount' => ['Op. gravada', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'igv' => ['IGV', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'service_charge' => ['R.C.', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'credit-notes' => [
            CreditNotesReport::class,
            [
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'payment-method-daily' => [
            PaymentMethodDailyReport::class,
            [
                'transactions' => ['Transacciones', '#,##0', XlsColumn::SUMMARY_SUM],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'payment-method-breakdown' => [
            PaymentMethodBreakdownReport::class,
            [
                'pm_1' => ['Efectivo', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'pm_2' => ['Yape', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'pm_other' => ['Otros métodos', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'total_paid' => ['Total pagado', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'sale_total' => ['Total comprobante', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'daily-summary' => [
            DailySummaryReport::class,
            [
                'sales' => ['Ventas', '#,##0', XlsColumn::SUMMARY_SUM],
                'subtotal' => ['Subtotal', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'tax' => ['IGV', '#,##0.00', XlsColumn::SUMMARY_SUM],
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
        yield 'voided-sales' => [
            VoidedSalesReport::class,
            [
                'total' => ['Total', '#,##0.00', XlsColumn::SUMMARY_SUM],
            ],
        ];
    }

    /**
     * @return iterable<string, array{0: class-string<ExportableReportInterface>, 1: array<string, string>, 2: list<string>}>
     */
    public static function reportExporterProvider(): iterable
    {
        yield 'sales-by-line' => [
            SalesByLineReport::class,
            [
                'quantity' => '1.100',
                'unit_price' => '14.0000',
                'discount' => '0.00',
                'total' => '15.40',
            ],
            ['quantity', 'discount', 'total'],
        ];
        yield 'detailed-sales' => [
            DetailedSalesReport::class,
            [
                'subtotal' => '100.00',
                'tax' => '18.00',
                'total' => '118.00',
                'discount' => '0.00',
            ],
            ['subtotal', 'tax', 'total', 'discount'],
        ];
        yield 'sale-by-product' => [
            SaleByProductReport::class,
            [
                'quantity' => '2.500',
                'average_unit_price' => '14.00',
                'total' => '35.00',
            ],
            ['quantity', 'total'],
        ];
        yield 'accounting-report' => [
            AccountingReport::class,
            [
                'taxable_amount' => '100.00',
                'igv' => '18.00',
                'service_charge' => '0.00',
                'total' => '118.00',
            ],
            ['taxable_amount', 'igv', 'service_charge', 'total'],
        ];
        yield 'credit-notes' => [
            CreditNotesReport::class,
            [
                'total' => '10.00',
            ],
            ['total'],
        ];
        yield 'payment-method-daily' => [
            PaymentMethodDailyReport::class,
            [
                'transactions' => '2',
                'total' => '50.00',
            ],
            ['transactions', 'total'],
        ];
        yield 'payment-method-breakdown' => [
            PaymentMethodBreakdownReport::class,
            [
                'pm_1' => '100.00',
                'pm_2' => '50.00',
                'pm_other' => '0.00',
                'total_paid' => '150.00',
                'sale_total' => '150.00',
            ],
            ['pm_1', 'pm_2', 'pm_other', 'total_paid', 'sale_total'],
        ];
        yield 'daily-summary' => [
            DailySummaryReport::class,
            [
                'sales' => '3',
                'subtotal' => '100.00',
                'tax' => '18.00',
                'total' => '118.00',
            ],
            ['sales', 'subtotal', 'tax', 'total'],
        ];
        yield 'voided-sales' => [
            VoidedSalesReport::class,
            [
                'total' => '20.00',
            ],
            ['total'],
        ];
    }

    /**
     * @param class-string<ExportableReportInterface> $reportClass
     */
    private function newReport(string $reportClass): ExportableReportInterface
    {
        if ($reportClass === PaymentMethodBreakdownReport::class) {
            $connection = $this->createStub(Connection::class);
            $connection
                ->method('fetchAllAssociative')
                ->willReturn([
                    ['id' => 1, 'name' => 'Efectivo'],
                    ['id' => 2, 'name' => 'Yape'],
                ]);

            return new PaymentMethodBreakdownReport($this->createStub(SunatStateService::class), $connection);
        }

        if (is_subclass_of($reportClass, AbstractSaleReport::class)) {
            return new $reportClass($this->createStub(SunatStateService::class));
        }

        return new $reportClass();
    }

    private static function assertNumericColumn(mixed $column, string $label, string $format, string $summary): void
    {
        self::assertInstanceOf(XlsColumnSpec::class, $column);
        self::assertSame($label, $column->label);
        self::assertContains($column->type, [XlsColumn::TYPE_NUMBER, XlsColumn::TYPE_INTEGER]);
        self::assertSame($format, $column->presentation->format);
        self::assertSame($summary, $column->summary->type);
    }
}
