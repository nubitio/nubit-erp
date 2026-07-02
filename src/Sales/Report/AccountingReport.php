<?php

declare(strict_types=1);

namespace App\Sales\Report;

use Nubit\Platform\Report\ExportableReportInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Accounting-report queries tax_document directly (not the sales table),
 * so it does not need the receipt filter and skips AbstractSaleReport.
 */
#[AutoconfigureTag('app.sale_report')]
final class AccountingReport implements ExportableReportInterface
{
    public function key(): string
    {
        return 'accounting-report';
    }

    public function fieldMap(): array
    {
        return [
            'issuedDate' => 'i.issue_date',
            'customer' => 'c.id',
            'invoiceFullNumber' => "concat(i.series, '-', i.number)",
            'grandTotal' => 'i.total',
        ];
    }

    public function sql(string $gridFilter = ''): string
    {
        return <<<SQL
            select distinct
                i.issue_date as issue_date,
                coalesce(vt.descripcion, 'Documento') as voucher_type,
                concat(i.series, '-', i.number) as document,
                coalesce(c.ruc, '-') as customer_ruc,
                coalesce(c.razon_social, 'Sin cliente') as customer_name,
                i.taxable_amount as taxable_amount,
                i.igv_amount as igv,
                i.rc_amount as service_charge,
                i.total,
                case
                    when s.status = 'VOIDED' then 'Anulado'
                    when i.sunat_status is null or i.sunat_status = '' then 'Pendiente'
                    when i.sunat_status = '0' then 'Aceptado'
                    when cast(i.sunat_status as unsigned) < 2000 then 'Erroneo'
                    when cast(i.sunat_status as unsigned) < 4000 then 'Rechazado'
                    else 'Observado'
                end as status
            from tax_document i
                inner join sales s on (s.legacy_movimiento_id = i.movimiento_id OR s.id = i.sale_id)
                left join cliente c on c.id = i.customer_id
                left join tipo_comprobante vt on vt.codigo = i.document_type
            where
                i.document_type in ('01', '03')
                and i.issue_date between :date_from and :date_to
                and i.branch_id = :local_id
                {$gridFilter}
            order by i.issue_date desc
            SQL;
    }

    public function columns(): array
    {
        return [
            'issue_date' => 'Fecha emisión',
            'voucher_type' => 'Tipo',
            'document' => 'Comprobante',
            'customer_ruc' => 'RUC/DNI',
            'customer_name' => 'Cliente',
            'taxable_amount' => 'Op. gravada',
            'igv' => 'IGV',
            'service_charge' => 'R.C.',
            'total' => 'Total',
            'status' => 'Estado',
        ];
    }

    public function filename(): string
    {
        return 'reporte_ventas_contable';
    }
}
