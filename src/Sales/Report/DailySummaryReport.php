<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Export\XlsColumnSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class DailySummaryReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'daily-summary';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 's.issued_date',
            'status' => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
            select
                s.issued_date as sale_date,
                count(*) as sales,
                sum(s.subtotal) as subtotal,
                sum(s.tax_total) as tax,
                sum(s.grand_total) as total
            from sales s
            where
                s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                and s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            group by sale_date
            order by sale_date desc
            SQL;
    }

    public function columns(): array
    {
        return [
            'sale_date' => 'Fecha',
            'sales' => XlsColumnSpec::integer('Ventas'),
            'subtotal' => XlsColumnSpec::number('Subtotal', '#,##0.00'),
            'tax' => XlsColumnSpec::number('IGV', '#,##0.00'),
            'total' => XlsColumnSpec::number('Total', '#,##0.00'),
        ];
    }

    public function filename(): string
    {
        return 'reporte_resumen_diario';
    }
}
