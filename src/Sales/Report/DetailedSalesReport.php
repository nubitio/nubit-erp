<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class DetailedSalesReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'detailed-sales';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate'        => 's.issued_date',
            'customer'          => 'c.id',
            'paymentMethod'     => 'pm.id',
            'invoiceFullNumber' => "concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-'))",
            'grandTotal'        => 's.grand_total',
            'status'            => 's.status',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    s.issued_date as sale_date,
    TIME(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_time,
    concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-')) as document,
    coalesce(vt.descripcion, 'N/A') as voucher_type,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc,
    coalesce(group_concat(distinct pm.name order by pm.name separator ', '), 'Sin método') as payment_method,
    s.subtotal,
    s.tax_total as tax,
    s.grand_total as total,
    s.discount_total as discount,
    case
        when s.status = 'VOIDED' then 'Anulado'
        else 'Aceptado'
    end as status
from sales s
    left join tax_document i on (i.movimiento_id = s.legacy_movimiento_id OR i.sale_id = s.id) and i.document_type in ('01', '03')
    left join tipo_comprobante vt on vt.codigo = coalesce(i.document_type, '00')
    left join cliente c on c.id = s.customer_id
    left join sale_payments sp on sp.sale_id = s.id
    left join payment_method pm on pm.id = sp.payment_method_id
where
    s.issued_date between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
    {$gridFilter}
group by
    s.id,
    sale_date,
    sale_time,
    document,
    voucher_type,
    customer_name,
    customer_ruc,
    s.subtotal,
    s.tax_total,
    s.grand_total,
    s.discount_total,
    status
order by s.issued_at desc
SQL;
    }

    public function columns(): array
    {
        return [
            'sale_date'      => 'Fecha',
            'sale_time'      => 'Hora',
            'document'       => 'Comprobante',
            'voucher_type'   => 'Tipo',
            'customer_name'  => 'Cliente',
            'customer_ruc'   => 'RUC/DNI',
            'payment_method' => 'Método de pago',
            'subtotal'       => 'Subtotal',
            'tax'            => 'IGV',
            'total'          => 'Total',
            'discount'       => 'Descuento',
            'status'         => 'Estado',
        ];
    }

    public function filename(): string
    {
        return 'reporte_ventas_detallado';
    }
}
