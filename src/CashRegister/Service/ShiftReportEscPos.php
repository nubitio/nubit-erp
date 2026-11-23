<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use DateTimeImmutable;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

/**
 * The X cut and the Z report of a shift as ESC/POS commands for the till printer, the same way the sale ticket is
 * built: the browser sends the bytes to the thermal printer (WebUSB or the local agent). It prints exactly what the
 * on-screen ticket shows, from the same data (`ShiftReport`).
 */
readonly class ShiftReportEscPos
{
    public function __construct(
        private TicketText $text = new TicketText(),
        private ShiftDrawerEscPos $drawer = new ShiftDrawerEscPos(),
    ) {}

    /**
     * @param array<string, mixed> $report The result of `ShiftReport::forSession()`.
     * @param int $width Characters per line: 32 for 58 mm paper, 48 for 80 mm.
     */
    public function commands(array $report, int $width): string
    {
        $width = max(32, $width);
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);
        $closed = $report['shift']['status'] === 'closed';
        $dashes = str_repeat('-', $width) . "\n";

        $this->header($printer, $report, $width, $closed, $dashes);
        $this->receipts($printer, $report, $width, $closed, $dashes);
        $this->methods($printer, $report, $width, $dashes);
        $this->drawer->drawer($printer, $report, $width, $closed, $dashes);
        $closed
            ? $this->drawer->closingFooter($printer, $report, $width, $dashes)
            : $this->drawer->informativeFooter($printer, $width);

        $printer->feed(3);
        $printer->cut();
        $data = $connector->getData();
        $printer->close();

        return $data;
    }

    /** @param array<string, mixed> $report */
    private function header(Printer $printer, array $report, int $width, bool $closed, string $dashes): void
    {
        $shift = $report['shift'];
        $company = $report['company'];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->text->strong($printer, $this->text->wrap((string) ($company['name'] ?? 'Nubit ERP'), $width));
        if (($company['ruc'] ?? null) !== null) {
            $printer->text('RUC ' . $company['ruc'] . "\n");
        }
        if (($company['branch'] ?? null) !== null) {
            $printer->text($this->text->wrap((string) $company['branch'], $width));
        }
        $printer->text($dashes);
        $this->text->strong($printer, $closed ? "REPORTE Z\nCIERRE DE TURNO\n" : "REPORTE X\nCORTE PARCIAL\n");
        $printer->text($dashes);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->text->row('Caja', (string) $shift['registerName'], $width));
        $printer->text($this->text->row('Turno', sprintf('T-%04d', $shift['id']), $width));
        if ($shift['cashier'] !== null) {
            $printer->text($this->text->row('Cajero', (string) $shift['cashier'], $width));
        }
        $printer->text($this->text->row('Apertura', $this->text->date($shift['openedAt']), $width));
        $printer->text(
            $closed
                ? $this->text->row('Cierre', $this->text->date($shift['closedAt']), $width)
                : $this->text->row('Emision', $this->text->date(new DateTimeImmutable()->format(DATE_ATOM)), $width),
        );
        if ($closed) {
            $printer->text($this->text->row('N. Z', sprintf('Z-%07d', $shift['id']), $width));
        }
        $printer->text($dashes);
    }

    /** @param array<string, mixed> $report */
    private function receipts(Printer $printer, array $report, int $width, bool $closed, string $dashes): void
    {
        $documents = $report['documents'];

        $this->text->title($printer, 'VENTAS POR COMPROBANTE');
        foreach ($documents['rows'] as $row) {
            $printer->text($this->text->row(
                sprintf('%s (%d)', $row['label'], $row['count']),
                $this->text->num($row['total']),
                $width,
            ));
        }
        $this->text->strong($printer, $this->text->row(
            'TOTAL VENTAS',
            $this->text->num($report['sales']['total']),
            $width,
        ));
        $printer->text($this->text->row('Op. gravada', $this->text->num($documents['taxable']), $width));
        $printer->text($this->text->row('IGV (18 %)', $this->text->num($documents['igv']), $width));
        if ($closed) {
            foreach ($documents['rows'] as $row) {
                foreach ($row['ranges'] as $range) {
                    $printer->text($this->text->wrap((string) $range, $width));
                }
            }
        }
        $printer->text($dashes);
    }

    /** @param array<string, mixed> $report */
    private function methods(Printer $printer, array $report, int $width, string $dashes): void
    {
        $this->text->title($printer, 'VENTAS POR MEDIO DE PAGO');
        foreach ($report['sales']['byMethod'] as $method) {
            $printer->text($this->text->row((string) $method['name'], $this->text->num($method['amount']), $width));
        }
        if ((float) $report['sales']['credit']['amount'] > 0) {
            $printer->text($this->text->row('Credito', $this->text->num($report['sales']['credit']['amount']), $width));
        }
        $printer->text($dashes);
    }
}
