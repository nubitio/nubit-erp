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
            $timestamp = mktime(
                hour: 0,
                minute: 0,
                second: 0,
                month: (int) substr($period, offset: 4, length: 2) - $i,
                day: 1,
                year: (int) substr($period, offset: 0, length: 4),
            );
            $p = $timestamp === false ? $period : date('Ym', $timestamp);
            $data = 0 === $i ? $current : $this->computePeriod($p);
            $incomeData = $data['ingresos'] ?? [];
            $costData = $data['costoVentas'] ?? [];
            assert(is_array($incomeData));
            assert(is_array($costData));

            $historial[] = [
                'periodo' => $p,
                'label' => $this->formatPeriodLabel($p),
                'ingresos' => $incomeData['neto'] ?? 0.0,
                'costoVentas' => $costData['monto'] ?? 0.0,
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
                COALESCE(SUM(CASE WHEN document_type IN ('01','03','08') THEN taxable_amount ELSE 0 END), 0) AS bruto,
                COALESCE(SUM(CASE WHEN document_type = '07' THEN taxable_amount ELSE 0 END), 0) AS notas_credito
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
        $costoVendido = (float) $this->connection->fetchOne("SELECT COALESCE(SUM(si.qty * si.unit_cost_snapshot), 0)
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE DATE_FORMAT(s.issued_at, '%Y%m') = :period
               AND s.status != 'VOIDED'
               AND si.unit_cost_snapshot IS NOT NULL", [
            'period' => $period,
        ]);

        $costoDevuelto = (float) $this->connection->fetchOne("SELECT COALESCE(SUM(ri.qty_returned * si.unit_cost_snapshot), 0)
             FROM return_items ri
             INNER JOIN returns r ON r.id = ri.return_id
             INNER JOIN sale_items si ON si.id = ri.sale_item_id
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE DATE_FORMAT(r.created_at, '%Y%m') = :period
               AND r.status = 'COMPLETED'
               AND s.status != 'VOIDED'
               AND si.unit_cost_snapshot IS NOT NULL", [
            'period' => $period,
        ]);
        $costoVentas = $costoVendido - $costoDevuelto;

        $utilidadBruta = $neto - $costoVentas;

        $gastosPorCategoriaRaw = $this->connection->fetchAllAssociative("SELECT ec.name AS categoria, COALESCE(SUM(sb.amount), 0) AS monto
             FROM supplier_bill sb
             INNER JOIN expense_category ec ON ec.id = sb.category_id
             WHERE DATE_FORMAT(sb.issue_date, '%Y%m') = :period
               AND sb.status != 'VOID'
               AND sb.purchase_id IS NULL
             GROUP BY ec.id, ec.name
             ORDER BY monto DESC", [
            'period' => $period,
        ]);

        $gastosPersonalRaw = $this->connection->fetchAllAssociative("SELECT ec.name AS categoria, COALESCE(SUM(er.amount), 0) AS monto
             FROM expense_report er
             INNER JOIN expense_category ec ON ec.id = er.category_id
             WHERE DATE_FORMAT(er.expense_date, '%Y%m') = :period
               AND er.status IN ('APPROVED', 'REIMBURSED')
             GROUP BY ec.id, ec.name", [
            'period' => $period,
        ]);

        $gastosAgrupados = [];
        foreach ([...$gastosPorCategoriaRaw, ...$gastosPersonalRaw] as $row) {
            $categoria = (string) $row['categoria'];
            $gastosAgrupados[$categoria] = ($gastosAgrupados[$categoria] ?? 0.0) + (float) $row['monto'];
        }
        arsort($gastosAgrupados);
        $gastosPorCategoria = array_map(
            static fn(string $categoria, float $monto): array => [
                'categoria' => $categoria,
                'monto' => $monto,
            ],
            array_keys($gastosAgrupados),
            array_values($gastosAgrupados),
        );
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
