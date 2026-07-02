<?php

declare(strict_types=1);

namespace App\Taxes\Application\Query;

use Doctrine\DBAL\Connection;

final readonly class TaxSummaryQuery
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed> */
    public function compute(string $period = ''): array
    {
        if ('' === $period) {
            $period = date('Ym');
        }

        $periodLabel = $this->formatPeriodLabel($period);
        $vencimiento = $this->computeVencimiento($period);

        // IGV ventas
        $ventas = $this->connection->fetchAssociative("SELECT COALESCE(SUM(taxable_amount),0) as base, COALESCE(SUM(igv_amount),0) as igv
             FROM tax_document
             WHERE document_type IN ('01','03')
               AND DATE_FORMAT(issue_date,'%Y%m') = :period
               AND void_ticket IS NULL
               AND void_correlation IS NULL", [
            'period' => $period,
        ]);

        // IGV compras
        $compras = $this->connection->fetchAssociative("SELECT COALESCE(SUM(subtotal),0) as base, COALESCE(SUM(tax_total),0) as igv
             FROM goods_receipts
             WHERE DATE_FORMAT(issued_at,'%Y%m') = :period
               AND status != 'VOIDED'", ['period' => $period]);

        // Retenciones sufridas
        $retenciones = $this->connection->fetchOne("SELECT COALESCE(SUM(amount),0) FROM retention_percepcion
             WHERE direction = 'sufrida'
               AND DATE_FORMAT(period_date,'%Y%m') = :period", [
            'period' => $period,
        ]);

        $debitoIgv = (float) ($ventas['igv'] ?? 0);
        $creditoIgv = (float) ($compras['igv'] ?? 0);
        $retencionesSufridas = (float) $retenciones;
        $igvPorPagar = max(0, $debitoIgv - $creditoIgv - $retencionesSufridas);

        // Historial — last 6 periods
        $historial = [];
        for ($i = 0; $i < 6; ++$i) {
            $p = date('Ym', mktime(0, 0, 0, (int) substr($period, 4, 2) - $i, 1, (int) substr($period, 0, 4)));
            $isCurrent = $p === $period;

            if ($isCurrent) {
                $historial[] = [
                    'periodo' => $p,
                    'label' => $this->formatPeriodLabel($p),
                    'igvVentas' => $debitoIgv,
                    'igvCompras' => $creditoIgv,
                    'igvPagar' => $igvPorPagar,
                    'estado' => 'abierto',
                ];
            } else {
                $hVentas = $this->connection->fetchOne(
                    "SELECT COALESCE(SUM(igv_amount),0) FROM tax_document
                     WHERE document_type IN ('01','03')
                       AND DATE_FORMAT(issue_date,'%Y%m') = :period
                       AND void_ticket IS NULL AND void_correlation IS NULL",
                    ['period' => $p],
                );
                $hCompras = $this->connection->fetchOne(
                    "SELECT COALESCE(SUM(tax_total),0) FROM goods_receipts
                     WHERE DATE_FORMAT(issued_at,'%Y%m') = :period AND status != 'VOIDED'",
                    ['period' => $p],
                );
                $historial[] = [
                    'periodo' => $p,
                    'label' => $this->formatPeriodLabel($p),
                    'igvVentas' => (float) $hVentas,
                    'igvCompras' => (float) $hCompras,
                    'igvPagar' => max(0, (float) $hVentas - (float) $hCompras - $retencionesSufridas),
                    'estado' => 'declarado',
                ];
            }
        }

        // KPI — detracciones pendientes
        $detraccionPendiente = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(detraction_amount),0) FROM tax_document
             WHERE detraction_amount IS NOT NULL AND detraction_amount > 0
               AND DATE_FORMAT(issue_date,'%Y%m') = :period
               AND detraction_deposited_at IS NULL
               AND void_ticket IS NULL AND void_correlation IS NULL",
            ['period' => $period],
        );

        $creditoFiscal = $creditoIgv;

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'vencimiento' => $vencimiento,
            'kpi' => [
                'igvPorPagar' => $igvPorPagar,
                'creditoFiscal' => $creditoFiscal,
                'detraccionesPendientes' => (float) $detraccionPendiente,
            ],
            'igvMensual' => [
                'debitoFiscal' => [
                    'baseImponible' => (float) ($ventas['base'] ?? 0),
                    'igv' => $debitoIgv,
                ],
                'creditoFiscal' => [
                    'baseImponible' => (float) ($compras['base'] ?? 0),
                    'igv' => $creditoIgv,
                ],
                'retencionesSufridas' => $retencionesSufridas,
                'igvPorPagar' => $igvPorPagar,
            ],
            'historial' => $historial,
        ];
    }

    private function formatPeriodLabel(string $period): string
    {
        $month = (int) substr($period, 4, 2);
        $year = (int) substr($period, 0, 4);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%d-%02d-01', $year, $month));

        if (!$date) {
            return $period;
        }

        $months = [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Setiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];

        return $months[$month - 1] . ' ' . $year;
    }

    private function computeVencimiento(string $period): string
    {
        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);

        // Due: 18th of the following month
        if (12 === $month) {
            return sprintf('%d-01-18', $year + 1);
        }

        return sprintf('%d-%02d-18', $year, $month + 1);
    }
}
