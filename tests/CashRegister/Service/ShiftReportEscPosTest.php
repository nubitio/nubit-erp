<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Service;

use App\CashRegister\Service\ShiftReportEscPos;
use PHPUnit\Framework\TestCase;

final class ShiftReportEscPosTest extends TestCase
{
    /** @return array<string, mixed> */
    private function report(bool $closed): array
    {
        return [
            'company' => [
                'name' => 'Comercial Andina S.A.C.',
                'ruc' => '20512345678',
                'branch' => 'Sucursal Principal',
            ],
            'shift' => [
                'id' => 481,
                'status' => $closed ? 'closed' : 'open',
                'registerName' => 'Caja 01',
                'cashier' => 'rosa',
                'closedBy' => null,
                'openedAt' => '2026-11-23T08:02:00-05:00',
                'closedAt' => $closed ? '2026-11-23T19:34:00-05:00' : null,
            ],
            'documents' => [
                'rows' => [
                    [
                        'type' => '03',
                        'label' => 'Boletas',
                        'count' => 74,
                        'ranges' => ['B001-00004101 a 00004174'],
                        'taxable' => '3356.27',
                        'igv' => '606.13',
                        'total' => '3962.40',
                    ],
                    [
                        'type' => '07',
                        'label' => 'N. de crédito',
                        'count' => 1,
                        'ranges' => ['BC01-00000007'],
                        'taxable' => '-84.75',
                        'igv' => '-15.25',
                        'total' => '-100.00',
                    ],
                ],
                'count' => 75,
                'taxable' => '3271.52',
                'igv' => '590.88',
                'total' => '3862.40',
            ],
            'sales' => [
                'total' => '3862.40',
                'byMethod' => [['name' => 'Efectivo', 'operations' => 31, 'amount' => '2310.40', 'percent' => '59.8']],
                'credit' => ['amount' => '800.00', 'operations' => 5, 'percent' => '20.7'],
            ],
            'cash' => [
                'opening' => '200.00',
                'cashSales' => '2310.40',
                'income' => '150.00',
                'expenses' => '85.10',
                'withdrawals' => '730.00',
                'expected' => '1845.30',
                'counted' => $closed ? '1840.30' : null,
                'difference' => $closed ? '-5.00' : null,
                'denominations' => null,
                'fundKept' => $closed ? true : null,
                'withdrawnAtClose' => $closed ? '1640.30' : null,
                'note' => $closed ? 'Vuelto mal entregado' : null,
            ],
            'outflows' => [],
        ];
    }

    public function testTheXCutIsInformativeAndFitsThePaper(): void
    {
        $bytes = new ShiftReportEscPos()->commands($this->report(false), 48);

        self::assertStringContainsString('REPORTE X', $bytes);
        self::assertStringContainsString('CORTE PARCIAL', $bytes);
        self::assertStringContainsString('Boletas (74)', $bytes);
        self::assertStringContainsString('3,962.40', $bytes);
        self::assertStringContainsString('REPORTE INFORMATIVO', $bytes);
        self::assertStringNotContainsString('B001-00004101', $bytes, 'the numbering ranges belong to the Z');
        self::assertStringNotContainsString('Supervisor', $bytes);
        // Every printed line stays within the width of the paper (ESC/POS commands aside).
        foreach (explode("\n", preg_replace('/[\x00-\x09\x0b-\x1f]|\x1b[@!a-z]./', '', $bytes) ?? '') as $line) {
            self::assertLessThanOrEqual(60, strlen($line));
        }
    }

    public function testTheZReportCarriesTheRangesTheDifferenceAndWhereTheCashWent(): void
    {
        $bytes = new ShiftReportEscPos()->commands($this->report(true), 48);

        self::assertStringContainsString('REPORTE Z', $bytes);
        self::assertStringContainsString('Z-0000481', $bytes);
        self::assertStringContainsString('B001-00004101 a 00004174', $bytes);
        self::assertStringContainsString('FALTANTE', $bytes);
        self::assertStringContainsString('Motivo: Vuelto mal entregado', $bytes);
        self::assertStringContainsString('Fondo siguiente turno', $bytes);
        self::assertStringContainsString('1,640.30', $bytes);
        self::assertStringContainsString('Supervisor', $bytes);
    }

    public function testAccentsAreSentAsThePrinterCanReadThem(): void
    {
        $bytes = new ShiftReportEscPos()->commands($this->report(false), 32);

        self::assertStringContainsString('N. de credito', $bytes);
        self::assertStringNotContainsString('é', $bytes);
    }
}
