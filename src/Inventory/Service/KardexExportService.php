<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\ApiResource\StockMovement;
use App\Inventory\Repository\StockMovementRepository;
use Doctrine\DBAL\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Twig\Environment;

final readonly class KardexExportService
{
    public function __construct(
        private StockMovementRepository $movementRepository,
        private KardexSummaryService $summaryService,
        private KardexReferenceResolver $referenceResolver,
        private Environment $twig,
    ) {
    }

    /**
     * @param array{
     *   productId: string,
     *   warehouseId?: string|null,
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
     */
    public function buildReportData(array $params): array
    {
        $productId = $params['productId'];
        $warehouseId = $params['warehouseId'] ?? null;
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $valuation = $params['valuation'] ?? null;

        $summary = $this->summaryService->getSummary($productId, $warehouseId, $from, $to, $valuation);

        $filters = array_filter([
            'product' => $productId,
            'warehouse' => $warehouseId,
            'from' => $from,
            'to' => $to,
            'valuation' => $valuation,
        ], static fn ($value) => $value !== null && $value !== '');

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
            'Fecha', 'Tipo', 'Referencia',
            'Cant.', 'Costo unit.', 'Total',
            'Cant.', 'Costo unit.', 'Total',
            'Stock', 'Valor',
        ];
        foreach ($headers as $index => $label) {
            $sheet->setCellValue([$index + 1, $headerRow], $label);
        }

        $dataRow = $headerRow + 1;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['occurredAt'],
                $row['movementType'],
                $row['reference'],
                $row['qtyIn'],
                $row['unitCostIn'],
                $row['lineValueIn'],
                $row['qtyOut'],
                $row['unitCostOut'],
                $row['lineValueOut'],
                $row['runningBalance'],
                $row['runningValue'],
            ], null, 'A' . $dataRow);
            ++$dataRow;
        }

        $lastRow = max($headerRow, $dataRow - 1);
        $sheet->getStyle('A' . $groupRow . ':K' . $lastRow)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFE5E7EB'));
        $sheet->getStyle('A' . $groupRow . ':K' . $headerRow)->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFCBD5E1'));

        foreach (['C', 'F', 'I'] as $dividerCol) {
            $sheet->getStyle($dividerCol . $groupRow . ':' . $dividerCol . $lastRow)->getBorders()->getRight()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF94A3B8'));
        }

        $sheet->getStyle('A' . $groupRow . ':K' . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $groupRow . ':K' . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8FAFC');
        $sheet->getStyle('A' . $groupRow . ':K' . $headerRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('D', 'K') as $column) {
            $sheet->getStyle($column . ($headerRow + 1) . ':' . $column . $lastRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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

    private function mapMovementRow(StockMovement $movement): array
    {
        return [
            'occurredAt' => $this->formatDate($movement->occurredAt),
            'movementType' => $movement->movementTypeLabel ?? $movement->movementType,
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

        return (new \DateTimeImmutable($value))
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
}