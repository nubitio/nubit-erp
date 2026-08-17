<?php

declare(strict_types=1);

namespace App\Reporting\Financial\Application\Query;

use Doctrine\DBAL\Connection;

final readonly class ProfitAndLossQuery
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

        $current = $this->computePeriod($period);

        // Historial — last 6 periods, current period first.
        $historial = [];
        for ($i = 0; $i < 6; ++$i) {
            $p = date('Ym', mktime(
                hour: 0,
                minute: 0,
                second: 0,
                month: (int) substr($period, offset: 4, length: 2) - $i,
                day: 1,
                year: (int) substr($period, offset: 0, length: 4),
            ));
            $data = 0 === $i ? $current : $this->computePeriod($p);

            $historial[] = [
                'periodo' => $p,
                'label' => $this->formatPeriodLabel($p),
                'ingresos' => $data['ingresos']['neto'],
                'costoVentas' => $data['costoVentas']['monto'],
                'utilidadNeta' => $data['utilidadNeta'],
            ];
        }

        return [
            'period' => $period,
            'periodLabel' => $this->formatPeriodLabel($period),
            ...$current,
            'historial' => $historial,
        ];
    }

    /** @return array<string, mixed> */
    private function computePeriod(string $period): array
    {
        $ingresosRow = $this->connection->fetchAssociative("SELECT
                COALESCE(SUM(CASE WHEN document_type IN ('01','03') THEN taxable_amount ELSE 0 END), 0) AS bruto,
                COALESCE(SUM(CASE WHEN document_type IN ('07','08') THEN taxable_amount ELSE 0 END), 0) AS notas_credito
             FROM tax_document
             WHERE DATE_FORMAT(issue_date, '%Y%m') = :period
               AND void_ticket IS NULL
               AND void_correlation IS NULL", [
            'period' => $period,
        ]);
        $ingresos = false !== $ingresosRow ? $ingresosRow : ['bruto' => 0, 'notas_credito' => 0];

        $bruto = (float) $ingresos['bruto'];
        $notasCredito = (float) $ingresos['notas_credito'];
        $neto = $bruto - $notasCredito;

        // Estimated COGS: the unit cost frozen on each sale item at the moment
        // of sale (Product::purchasePrice snapshot — see ProfitAndLossQuery
        // class doc / plan notes for why this beats a live Kardex replay for
        // past periods).
        $costoVentas = (float) $this->connection->fetchOne("SELECT COALESCE(SUM(si.qty * si.unit_cost_snapshot), 0)
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE DATE_FORMAT(s.issued_at, '%Y%m') = :period
               AND s.status != 'VOIDED'
               AND si.unit_cost_snapshot IS NOT NULL", [
            'period' => $period,
        ]);

        $utilidadBruta = $neto - $costoVentas;

        $gastosPorCategoriaRaw = $this->connection->fetchAllAssociative("SELECT ec.name AS categoria, COALESCE(SUM(sb.amount), 0) AS monto
             FROM supplier_bill sb
             INNER JOIN expense_category ec ON ec.id = sb.category_id
             WHERE DATE_FORMAT(sb.issue_date, '%Y%m') = :period
               AND sb.status != 'VOID'
             GROUP BY ec.id, ec.name
             ORDER BY monto DESC", [
            'period' => $period,
        ]);

        $gastosPorCategoria = array_map(static fn(array $row): array => [
            'categoria' => $row['categoria'],
            'monto' => (float) $row['monto'],
        ], $gastosPorCategoriaRaw);
        $gastosOperativos = (float) array_sum(array_column($gastosPorCategoria, 'monto'));

        $utilidadNeta = $utilidadBruta - $gastosOperativos;

        return [
            'ingresos' => [
                'bruto' => $bruto,
                'notasCredito' => $notasCredito,
                'neto' => $neto,
            ],
            'costoVentas' => [
                'monto' => $costoVentas,
                'isEstimate' => true,
            ],
            'utilidadBruta' => $utilidadBruta,
            'gastosOperativos' => [
                'total' => $gastosOperativos,
                'porCategoria' => $gastosPorCategoria,
            ],
            'utilidadNeta' => $utilidadNeta,
        ];
    }

    private function formatPeriodLabel(string $period): string
    {
        $month = (int) substr($period, offset: 4, length: 2);
        $year = (int) substr($period, offset: 0, length: 4);
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
}
