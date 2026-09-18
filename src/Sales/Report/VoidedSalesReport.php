<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Export\XlsColumnSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class VoidedSalesReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'voided-sales';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 's.issued_date',
            'customer' => 'c.id',
            'invoiceFullNumber' => "concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-'))",
            'grandTotal' => 's.grand_total',
            'status' => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
            select
                s.issued_date as sale_date,
                concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-')) as document,
                coalesce(vt.descripcion, 'N/A') as voucher_type,
                coalesce(c.razon_social, 'Sin cliente') as customer_name,
                coalesce(c.ruc, '-') as customer_ruc,
                s.grand_total as total,
                case
                    when s.status = 'VOIDED' then 'Anulado'
                    else 'Aceptado'
                end as status
            from sales s
                left join tax_document i on (i.sale_id = s.id OR i.movimiento_id = s.id) and i.document_type in ('01', '03')
                left join cliente c on c.id = s.customer_id
                left join tipo_comprobante vt on vt.codigo = coalesce(i.document_type, '00')
            where
                s.status = 'VOIDED'
                and s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            order by s.issued_at desc
            SQL;
    }

    public function columns(): array
    {
        return [
            'sale_date' => 'Fecha',
            'document' => 'Comprobante',
            'voucher_type' => 'Tipo',
            'customer_name' => 'Cliente',
            'customer_ruc' => 'RUC/DNI',
            'total' => XlsColumnSpec::number('Total', '#,##0.00'),
            'status' => 'Estado',
        ];
    }

    public function filename(): string
    {
        return 'reporte_ventas_anuladas';
    }
}
