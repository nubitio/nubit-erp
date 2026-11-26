<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\ApiResource\StockMovement;
use App\Inventory\Repository\StockMovementRepository;
use Doctrine\DBAL\Exception;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twig\Environment;

final readonly class KardexExportService
{
    public const int MAX_EXPORT_ROWS = 10_000;

    public function __construct(
        private StockMovementRepository $movementRepository,
        private KardexSummaryService $summaryService,
        private KardexReferenceResolver $referenceResolver,
        private Environment $twig,
    ) {}

    /**
     * @param array{
     *   productId: string,
     *   warehouseId?: string|null,
     *   branchId?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   valuation?: string|null,
     * } $params
     *
     * @return array{
     *   summary: array<string, mixed>,
     *   movements: list<StockMovement>,
     *   rows: list<array<string, string|null>>,
     * }
     *
     * @throws Exception
     * @throws KardexExportLimitExceeded
     */
    public function buildReportData(array $params): array
    {
        $productId = $params['productId'];
        $branchId = $params['branchId'] ?? null;
        $warehouseId = $params['warehouseId'] ?? null;
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $valuation = $params['valuation'] ?? null;

        $filters = array_filter(
            [
                'product' => $productId,
                'warehouse' => $warehouseId,
                'branchId' => $branchId,
                'from' => $from,
                'to' => $to,
                'valuation' => $valuation,
            ],
            static fn($value) => $value !== null && $value !== '',
        );
        /** @var array{product?: string, warehouse?: string, branchId?: string, from?: string, to?: string, valuation?: string} $filters */

        $movementCount = $this->movementRepository->countKardex($filters);
        if ($movementCount > self::MAX_EXPORT_ROWS) {
            throw new KardexExportLimitExceeded(self::MAX_EXPORT_ROWS);
        }

        $summary = $this->summaryService->getSummary($productId, $warehouseId, $from, $to, $valuation, $branchId);

        $movements = $this->movementRepository->findKardexForExport($filters);
        $this->referenceResolver->applyToMovements($movements);
        $rows = array_map($this->mapMovementRow(...), $movements);

        return [
            'summary' => $summary,
            'movements' => $movements,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function renderPdfHtml(array $report): string
    {
        return $this->twig->render('inventory/kardex_report.html.twig', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
        ]);
    }

    /**
     * @param array<string, mixed> $report
     */
    public function buildSpreadsheet(array $report): Spreadsheet
    {
        $summary = $report['summary'];
        $rows = $report['rows'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kardex');

        $sheet->setCellValue('A1', 'Kardex de inventario');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $product = $summary['product'];
        $sheet->setCellValue('A2', sprintf(
            '%s%s · %s',
            $product['code'] ? $product['code'] . ' - ' : '',
            $product['name'],
            $summary['periodLabel'],
        ));
        $sheet->mergeCells('A2:K2');

        $sheet->setCellValue('A3', sprintf(
            'Stock: %s · Valor: %s · Método: %s',
            $summary['currentStock'],
            $summary['stockValue'],
            $summary['valuationMethod'] === 'fifo' ? 'FIFO' : 'Costo prom.',
        ));
        $sheet->mergeCells('A3:K3');

        $groupRow = 5;
        $sheet->setCellValue('A' . $groupRow, 'DOCUMENTO');
        $sheet->mergeCells('A' . $groupRow . ':C' . $groupRow);
        $sheet->setCellValue('D' . $groupRow, 'ENTRADAS');
        $sheet->mergeCells('D' . $groupRow . ':F' . $groupRow);
        $sheet->setCellValue('G' . $groupRow, 'SALIDAS');
        $sheet->mergeCells('G' . $groupRow . ':I' . $groupRow);
        $sheet->setCellValue('J' . $groupRow, 'SALDO');
        $sheet->mergeCells('J' . $groupRow . ':K' . $groupRow);

        $headerRow = $groupRow + 1;
        $headers = [
            'Fecha',
            'Tipo',
            'Referencia',
            'Cant.',
            'Costo unit.',
            'Total',
            'Cant.',
            'Costo unit.',
            'Total',
            'Stock',
            'Valor',
        ];
        foreach ($headers as $index => $label) {
            $sheet->setCellValue([$index + 1, $headerRow], $label);
        }

        $dataRow = $headerRow + 1;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit('A' . $dataRow, $row['occurredAt'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $dataRow, $row['movementType'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $dataRow, $row['reference'], DataType::TYPE_STRING);

            foreach ([
                'D' => $row['qtyIn'],
                'E' => $row['unitCostIn'],
                'F' => $row['lineValueIn'],
                'G' => $row['qtyOut'],
                'H' => $row['unitCostOut'],
                'I' => $row['lineValueOut'],
                'J' => $row['runningBalance'],
                'K' => $row['runningValue'],
            ] as $column => $value) {
                $number = $this->numericCellValue($value);
                if ($number === null) {
                    $sheet->setCellValueExplicit($column . $dataRow, '', DataType::TYPE_STRING);
                    continue;
                }

                $sheet->setCellValueExplicit($column . $dataRow, $number, DataType::TYPE_NUMERIC);
            }

            ++$dataRow;
        }

        $lastRow = max($headerRow, $dataRow - 1);
        $totalsRow = $lastRow + 1;
        if ($lastRow >= ($headerRow + 1)) {
            $sheet->setCellValueExplicit('C' . $totalsRow, 'TOTALES', DataType::TYPE_STRING);
            foreach (['D', 'F', 'G', 'I'] as $column) {
                $sheet->setCellValue($column . $totalsRow, sprintf(
                    '=SUM(%s%d:%s%d)',
                    $column,
                    $headerRow + 1,
                    $column,
                    $lastRow,
                ));
            }
        }

        $styledLastRow = $lastRow >= ($headerRow + 1) ? $totalsRow : $lastRow;
        $sheet
            ->getStyle('A' . $groupRow . ':K' . $styledLastRow)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFE5E7EB'));
        $sheet
            ->getStyle('A' . $groupRow . ':K' . $headerRow)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFCBD5E1'));

        foreach (['C', 'F', 'I'] as $dividerCol) {
            $sheet
                ->getStyle($dividerCol . $groupRow . ':' . $dividerCol . $styledLastRow)
                ->getBorders()
                ->getRight()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF94A3B8'));
        }

        $sheet
            ->getStyle('A' . $groupRow . ':K' . $headerRow)
            ->getFont()
            ->setBold(true);
        $sheet
            ->getStyle('A' . $groupRow . ':K' . $headerRow)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFF8FAFC');
        $sheet
            ->getStyle('A' . $groupRow . ':K' . $headerRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('D', 'K') as $column) {
            $sheet
                ->getStyle($column . ($headerRow + 1) . ':' . $column . $styledLastRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach (['D', 'G', 'J'] as $quantityColumn) {
            $sheet
                ->getStyle($quantityColumn . ($headerRow + 1) . ':' . $quantityColumn . $styledLastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0.###');
        }

        foreach (['E', 'F', 'H', 'I', 'K'] as $moneyColumn) {
            $sheet
                ->getStyle($moneyColumn . ($headerRow + 1) . ':' . $moneyColumn . $styledLastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        if ($lastRow >= ($headerRow + 1)) {
            $sheet
                ->getStyle('A' . $totalsRow . ':K' . $totalsRow)
                ->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFCBD5E1']],
                    ],
                ]);
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function streamSpreadsheet(Spreadsheet $spreadsheet): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * @return array<string, string>
     */
    private function mapMovementRow(StockMovement $movement): array
    {
        return [
            'occurredAt' => $this->formatDate($movement->occurredAt),
            'movementType' => $movement->movementTypeLabel ?? $movement->movementType ?? '',
            'reference' => $movement->sourceReference ?? '—',
            'qtyIn' => $this->formatQty($movement->qtyIn),
            'unitCostIn' => $movement->qtyIn ? $this->formatMoney($movement->unitCost) : '—',
            'lineValueIn' => $movement->qtyIn ? $this->formatMoney($movement->lineValue) : '—',
            'qtyOut' => $this->formatQty($movement->qtyOut),
            'unitCostOut' => $movement->qtyOut ? $this->formatMoney($movement->unitCost) : '—',
            'lineValueOut' => $movement->qtyOut ? $this->formatMoney($movement->lineValue) : '—',
            'runningBalance' => $this->formatQty($movement->runningBalance),
            'runningValue' => $this->formatMoney($movement->runningValue),
        ];
    }

    private function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return new \DateTimeImmutable($value)
            ->setTimezone(new \DateTimeZone('America/Lima'))
            ->format('d/m/Y');
    }

    private function formatQty(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;
        if (abs($number) < 0.000001) {
            return '—';
        }

        return rtrim(rtrim(number_format($number, 3, '.', ','), '0'), '.');
    }

    private function formatMoney(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;
        if (abs($number) < 0.000001) {
            return '—';
        }

        return 'S/ ' . number_format($number, 2, '.', ',');
    }

    private function numericCellValue(?string $value): ?float
    {
        if ($value === null || $value === '' || $value === '—') {
            return null;
        }

        $normalized = str_replace(['S/', ',', ' '], '', $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
