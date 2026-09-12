<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\Repository\StockMovementRepository;
use App\Inventory\Service\FifoKardexValuator;
use App\Inventory\Service\KardexCostSqlHelper;
use App\Inventory\Service\KardexExportLimitExceeded;
use App\Inventory\Service\KardexExportService;
use App\Inventory\Service\KardexReferenceResolver;
use App\Inventory\Service\KardexSummaryService;
use App\Inventory\Service\WeightedAverageKardexValuator;
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class KardexExportServiceSpreadsheetTest extends TestCase
{
    public function testExportRejectsMoreThanTheDocumentedMovementCeiling(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(KardexExportService::MAX_EXPORT_ROWS + 1);
        $costSql = new KardexCostSqlHelper($connection);
        $movements = new StockMovementRepository(
            $connection,
            new FifoKardexValuator(),
            new WeightedAverageKardexValuator(),
            $costSql,
        );

        $service = new KardexExportService(
            $movements,
            new KardexSummaryService(
                $connection,
                new FifoKardexValuator(),
                new WeightedAverageKardexValuator(),
                $costSql,
            ),
            new KardexReferenceResolver($connection),
            new \ReflectionClass(Environment::class)->newInstanceWithoutConstructor(),
        );

        $this->expectException(KardexExportLimitExceeded::class);
        $this->expectExceptionMessage((string) KardexExportService::MAX_EXPORT_ROWS);

        $service->buildReportData(['productId' => 'product-id']);
    }

    public function testSpreadsheetUsesNumericCellsAndMovementTotals(): void
    {
        $service = new \ReflectionClass(KardexExportService::class)->newInstanceWithoutConstructor();

        $spreadsheet = $service->buildSpreadsheet([
            'summary' => [
                'product' => ['code' => 'P001', 'name' => 'Producto'],
                'periodLabel' => 'Julio 2026',
                'currentStock' => '8',
                'stockValue' => '80.00',
                'valuationMethod' => 'pp',
            ],
            'rows' => [
                [
                    'occurredAt' => '01/07/2026',
                    'movementType' => 'Entrada',
                    'reference' => 'OC-1',
                    'qtyIn' => '10',
                    'unitCostIn' => 'S/ 5.00',
                    'lineValueIn' => 'S/ 50.00',
                    'qtyOut' => '—',
                    'unitCostOut' => '—',
                    'lineValueOut' => '—',
                    'runningBalance' => '10',
                    'runningValue' => 'S/ 50.00',
                ],
                [
                    'occurredAt' => '02/07/2026',
                    'movementType' => 'Salida',
                    'reference' => 'V-1',
                    'qtyIn' => '—',
                    'unitCostIn' => '—',
                    'lineValueIn' => '—',
                    'qtyOut' => '2',
                    'unitCostOut' => 'S/ 5.00',
                    'lineValueOut' => 'S/ 10.00',
                    'runningBalance' => '8',
                    'runningValue' => 'S/ 40.00',
                ],
            ],
        ]);

        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('D7')->getDataType());
        self::assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('F7')->getDataType());
        self::assertSame(10.0, $sheet->getCell('D7')->getValue());
        self::assertSame(50.0, $sheet->getCell('F7')->getValue());
        self::assertSame('', $sheet->getCell('G7')->getValue());
        self::assertSame('TOTALES', $sheet->getCell('C9')->getValue());
        self::assertSame('=SUM(D7:D8)', $sheet->getCell('D9')->getValue());
        self::assertSame('=SUM(F7:F8)', $sheet->getCell('F9')->getValue());
        self::assertSame('=SUM(G7:G8)', $sheet->getCell('G9')->getValue());
        self::assertSame('=SUM(I7:I8)', $sheet->getCell('I9')->getValue());
    }
}
