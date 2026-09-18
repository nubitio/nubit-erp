<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Export\XlsColumn;
use Nubit\Platform\Export\XlsColumnSpec;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.sale_report')]
final class SalesByLineReport extends AbstractSaleReport
{
    public function key(): string
    {
        return 'sales-by-line';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 's.issued_date',
            'customer' => 'c.id',
            'paymentMethod' => 'sp_filter.payment_method_id',
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
                TIME(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_time,
                concat(coalesce(i.series, s.series, '-'), '-', coalesce(i.number, s.number, '-')) as document,
                coalesce(vt.descripcion, 'N/A') as voucher_type,
                coalesce(c.razon_social, 'Sin cliente') as customer_name,
                coalesce(c.ruc, '-') as customer_ruc,
                coalesce(pm.payment_methods, 'Sin método') as payment_method,
                coalesce(p.codigo, '-') as product_code,
                coalesce(p.descripcion, 'Sin producto') as base_product,
                coalesce(nullif(si.name_snapshot, ''), p.descripcion, 'Sin descripción') as line_description,
                coalesce(cat.descripcion, '-') as category,
                si.qty as quantity,
                coalesce(si.uom_snapshot, u.code, '-') as unit,
                si.unit_price as unit_price,
                si.discount_amount as discount,
                si.net_amount as total,
                case
                    when s.status = 'VOIDED' then 'Anulado'
                    else 'Aceptado'
                end as status
            from sales s
                inner join sale_items si on si.sale_id = s.id
                left join tax_document i on (i.sale_id = s.id OR i.movimiento_id = s.id) and i.document_type in ('01', '03')
                left join tipo_comprobante vt on vt.codigo = coalesce(i.document_type, '00')
                left join cliente c on c.id = s.customer_id
                left join producto p on p.producto_id = si.product_id
                left join categoria cat on cat.categoria_id = p.categoria_id
                left join unidad u on u.unidad_id = p.unidad_id
                left join sale_payments sp_filter on sp_filter.sale_id = s.id
                left join (
                    select
                        sp.sale_id,
                        group_concat(distinct pay.name order by pay.name separator ', ') as payment_methods
                    from sale_payments sp
                        left join payment_method pay on pay.id = sp.payment_method_id
                    group by sp.sale_id
                ) pm on pm.sale_id = s.id
            where
                s.issued_date between :date_from and :date_to
                and s.branch_id = :local_id
                {$receiptFilter}
                {$gridFilter}
            group by
                si.id,
                sale_date,
                sale_time,
                document,
                voucher_type,
                customer_name,
                customer_ruc,
                payment_method,
                product_code,
                base_product,
                line_description,
                category,
                quantity,
                unit,
                unit_price,
                discount,
                total,
                status
            order by s.issued_at desc, si.line_no asc
            SQL;
    }

    public function columns(): array
    {
        return [
            'sale_date' => 'Fecha',
            'sale_time' => 'Hora',
            'document' => 'Comprobante',
            'voucher_type' => 'Tipo',
            'customer_name' => 'Cliente',
            'customer_ruc' => 'RUC/DNI',
            'payment_method' => 'Método de pago',
            'product_code' => 'Código',
            'base_product' => 'Producto base',
            'line_description' => 'Descripción',
            'category' => 'Categoría',
            'quantity' => XlsColumnSpec::number('Cantidad', '#,##0.###'),
            'unit' => 'Unidad',
            'unit_price' => XlsColumnSpec::number('Precio unit.', '#,##0.0000', XlsColumn::SUMMARY_NONE),
            'discount' => XlsColumnSpec::number('Descuento', '#,##0.00'),
            'total' => XlsColumnSpec::number('Total', '#,##0.00'),
            'status' => 'Estado',
        ];
    }

    public function filename(): string
    {
        return 'reporte_ventas_por_linea';
    }
}
