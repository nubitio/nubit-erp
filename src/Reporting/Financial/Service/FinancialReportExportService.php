<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Service;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final readonly class FinancialReportExportService
{
    /**
     * @param array<string, mixed> $pnl
     * @param array<string, mixed> $cashFlow
     */
    public function buildSpreadsheet(array $pnl, array $cashFlow): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildPnlSheet($spreadsheet, $pnl);
        $this->buildCashFlowSheet($spreadsheet, $cashFlow);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function streamSpreadsheet(Spreadsheet $spreadsheet): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /** @param array<string, mixed> $pnl */
    private function buildPnlSheet(Spreadsheet $spreadsheet, array $pnl): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('P&L');

        $sheet->setCellValue('A1', 'Estado de resultados');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', $pnl['periodLabel'] ?? '');
        $sheet->mergeCells('A2:B2');

        $row = 4;
        $this->writeLabelHeader($sheet, $row);
        ++$row;

        $ingresos = $pnl['ingresos'];
        $row = $this->writeMoneyRow($sheet, $row, 'Ingresos brutos', (float) $ingresos['bruto']);
        $row = $this->writeMoneyRow($sheet, $row, 'Notas de crédito', -(float) $ingresos['notasCredito']);
        $row = $this->writeBoldMoneyRow($sheet, $row, 'Ingresos netos', (float) $ingresos['neto']);

        ++$row;
        $row = $this->writeMoneyRow($sheet, $row, 'Costo de ventas (estimado)', -(float) $pnl['costoVentas']['monto']);
        $row = $this->writeBoldMoneyRow($sheet, $row, 'Utilidad bruta', (float) $pnl['utilidadBruta']);

        ++$row;
        $sheet->setCellValueExplicit('A' . $row, 'Gastos operativos', DataType::TYPE_STRING);
        ++$row;
        foreach ($pnl['gastosOperativos']['porCategoria'] as $gasto) {
            $row = $this->writeMoneyRow($sheet, $row, '  ' . $gasto['categoria'], -(float) $gasto['monto']);
        }
        $row = $this->writeBoldMoneyRow(
            $sheet,
            $row,
            'Total gastos operativos',
            -(float) $pnl['gastosOperativos']['total'],
        );

        ++$row;
        $row = $this->writeBoldMoneyRow($sheet, $row, 'Utilidad neta', (float) $pnl['utilidadNeta']);

        $sheet
            ->getStyle('B4:B' . $row)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    /** @param array<string, mixed> $cashFlow */
    private function buildCashFlowSheet(Spreadsheet $spreadsheet, array $cashFlow): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Flujo de caja');

        $sheet->setCellValue('A1', 'Flujo de caja');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', sprintf('%s a %s', $cashFlow['from'], $cashFlow['to']));
        $sheet->mergeCells('A2:D2');

        $row = 4;
        $sheet->setCellValueExplicit('A' . $row, 'Semana', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, 'Entradas', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $row, 'Salidas', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $row, 'Neto', DataType::TYPE_STRING);
        $this->boldHeaderRow($sheet, $row, 'D');
        ++$row;

        foreach ($cashFlow['real'] as $week) {
            $sheet->setCellValueExplicit('A' . $row, $week['weekKey'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, $week['entradas'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('C' . $row, $week['salidas'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('D' . $row, $week['neto'], DataType::TYPE_NUMERIC);
            ++$row;
        }

        $row += 2;
        $sheet->setCellValueExplicit('A' . $row, 'Proyección (próximos 30 días)', DataType::TYPE_STRING);
        $sheet
            ->getStyle('A' . $row)
            ->getFont()
            ->setBold(true);
        ++$row;

        $sheet->setCellValueExplicit('A' . $row, 'Semana', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, 'Cobros esperados', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $row, 'Pagos esperados', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $row, 'Neto esperado', DataType::TYPE_STRING);
        $this->boldHeaderRow($sheet, $row, 'D');
        ++$row;

        foreach ($cashFlow['proyectado'] as $week) {
            $sheet->setCellValueExplicit('A' . $row, $week['weekKey'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, $week['cobrosEsperados'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('C' . $row, $week['pagosEsperados'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('D' . $row, $week['netoEsperado'], DataType::TYPE_NUMERIC);
            ++$row;
        }

        $sheet
            ->getStyle('B5:D' . $row)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        foreach (range('A', end: 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeLabelHeader(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValueExplicit('A' . $row, 'Concepto', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, 'Monto', DataType::TYPE_STRING);
        $this->boldHeaderRow($sheet, $row, 'B');
    }

    private function boldHeaderRow(Worksheet $sheet, int $row, string $lastColumn): void
    {
        $sheet
            ->getStyle('A' . $row . ':' . $lastColumn . $row)
            ->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']],
                ],
            ]);
    }

    private function writeMoneyRow(Worksheet $sheet, int $row, string $label, float $amount): int
    {
        $sheet->setCellValueExplicit('A' . $row, $label, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, $amount, DataType::TYPE_NUMERIC);

        return $row + 1;
    }

    private function writeBoldMoneyRow(Worksheet $sheet, int $row, string $label, float $amount): int
    {
        $next = $this->writeMoneyRow($sheet, $row, $label, $amount);
        $sheet
            ->getStyle('A' . $row . ':B' . $row)
            ->getFont()
            ->setBold(true);

        return $next;
    }
}
