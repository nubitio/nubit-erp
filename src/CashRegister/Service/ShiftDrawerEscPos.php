<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Mike42\Escpos\Printer;

/** The cash half of the shift ticket: what the drawer holds, the count, and how the report ends. */
readonly class ShiftDrawerEscPos
{
    public function __construct(
        private TicketText $text = new TicketText(),
    ) {}

    /** @param array<string, mixed> $report */
    public function drawer(Printer $printer, array $report, int $width, bool $closed, string $dashes): void
    {
        $cash = $report['cash'];

        $this->text->title($printer, $closed ? 'ARQUEO DE EFECTIVO' : 'EFECTIVO EN CAJA');
        $printer->text($this->text->row('Fondo inicial', $this->text->num($cash['opening']), $width));
        $printer->text($this->text->row('+ Ventas en efectivo', $this->text->num($cash['cashSales']), $width));
        $printer->text($this->text->row('+ Ingresos', $this->text->num($cash['income']), $width));
        $printer->text($this->text->row('- Egresos', $this->text->num($cash['expenses']), $width));
        $printer->text($this->text->row('- Retiros', $this->text->num($cash['withdrawals']), $width));
        $this->text->strong($printer, $this->text->row(
            $closed ? 'Esperado' : 'EFECTIVO ESPERADO',
            $this->text->num($cash['expected']),
            $width,
        ));
        if ($closed && $cash['counted'] !== null) {
            $this->counted($printer, $cash, $width);
        }
        $printer->text($dashes);
    }

    /** @param array<string, mixed> $cash */
    private function counted(Printer $printer, array $cash, int $width): void
    {
        $this->text->strong($printer, $this->text->row('Contado', $this->text->num($cash['counted']), $width));
        $difference = (float) $cash['difference'];
        $label = $difference === 0.0 ? 'CUADRADO' : ($difference < 0 ? 'FALTANTE' : 'SOBRANTE');
        $this->text->strong($printer, $this->text->row(
            'DIFERENCIA',
            sprintf('S/ %s %s', $this->text->num((string) abs($difference)), $label),
            $width,
        ));
        if ($difference !== 0.0 && $cash['note'] !== null) {
            $printer->text($this->text->wrap('Motivo: ' . $cash['note'], $width));
        }
    }

    /** @param array<string, mixed> $report */
    public function closingFooter(Printer $printer, array $report, int $width, string $dashes): void
    {
        $cash = $report['cash'];

        $this->text->title($printer, 'MEDIOS SIN EFECTIVO');
        $printer->text($this->text->wrap(
            'Tarjeta, Yape, Plin y transferencia se concilian con el terminal, la app o el banco.',
            $width,
        ));
        $printer->text($dashes);

        if ($cash['fundKept'] !== null && $cash['withdrawnAtClose'] !== null) {
            $this->text->title($printer, 'DESTINO DEL EFECTIVO');
            $printer->text($this->text->row(
                'Fondo siguiente turno',
                $this->text->num($cash['fundKept'] ? $cash['opening'] : '0'),
                $width,
            ));
            $printer->text($this->text->row(
                'Retirado de la caja',
                $this->text->num($cash['withdrawnAtClose']),
                $width,
            ));
            $printer->text($dashes);
        }

        $printer->feed(3);
        $half = intdiv($width - 2, 2);
        $printer->text(str_repeat('_', $half) . '  ' . str_repeat('_', $half) . "\n");
        $printer->text(
            str_pad('Cajero', $half, ' ', STR_PAD_BOTH) . '  ' . str_pad('Supervisor', $half, ' ', STR_PAD_BOTH) . "\n",
        );
    }

    public function informativeFooter(Printer $printer, int $width): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->text->strong($printer, "REPORTE INFORMATIVO\n");
        $printer->text($this->text->wrap('No cierra el turno ni reinicia contadores.', $width));
    }
}
