<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\SettingsService;
use App\Core\Service\XlsExporter;
use App\Core\Util\Constants;
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SaleReportController extends AbstractController
{
    public function __construct(
        private readonly XlsExporter $xlsExporter,
        private readonly Connection $connection,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function __invoke(Request $request, BranchContextService $branchContext): StreamedResponse
    {
        $report = (string)$request->query->get('report', '');
        $dateFrom = (string)$request->query->get('date-from', '');
        $dateTo = (string)$request->query->get('date-to', '');
        $localId = $branchContext->getCurrentBranchId();
        if ($localId === null) {
            throw new BadRequestHttpException('Debe seleccionar una sucursal para generar el reporte.');
        }

        if ($report === '') {
            throw new BadRequestHttpException('El tipo de reporte es requerido.');
        }

        $params = $this->buildDateRangeParams($localId, $dateFrom, $dateTo);

        switch ($report) {
            case 'sale-by-product':
                $data = $this->connection->fetchAllAssociative($this->saleByProductSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_por_producto', [
                    'product_code' => 'Código',
                    'product_name' => 'Producto',
                    'category' => 'Categoría',
                    'quantity' => 'Cantidad',
                    'average_unit_price' => 'Precio unit. prom.',
                    'total' => 'Total',
                ]);
                break;
            case 'detailed-sales':
                $data = $this->connection->fetchAllAssociative($this->detailedSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_detallado', [
                    'sale_date' => 'Fecha',
                    'sale_time' => 'Hora',
                    'document' => 'Comprobante',
                    'voucher_type' => 'Tipo',
                    'customer_name' => 'Cliente',
                    'customer_ruc' => 'RUC/DNI',
                    'payment_method' => 'Método de pago',
                    'subtotal' => 'Subtotal',
                    'tax' => 'IGV',
                    'total' => 'Total',
                    'discount' => 'Descuento',
                    'status' => 'Estado',
                ]);
                break;
            case 'accounting-report':
                $data = $this->connection->fetchAllAssociative($this->accountingSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_contable', [
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
                ]);
                break;
            case 'voided-sales':
                $data = $this->connection->fetchAllAssociative($this->voidedSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_anuladas', [
                    'sale_date' => 'Fecha',
                    'document' => 'Comprobante',
                    'voucher_type' => 'Tipo',
                    'customer_name' => 'Cliente',
                    'customer_ruc' => 'RUC/DNI',
                    'total' => 'Total',
                    'status' => 'Estado',
                ]);
                break;
            case 'credit-notes':
                $data = $this->connection->fetchAllAssociative($this->creditNotesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_notas_de_credito', [
                    'issue_date' => 'Fecha emisión',
                    'document' => 'Comprobante',
                    'note_type' => 'Tipo nota',
                    'reason_code' => 'Código motivo',
                    'reason' => 'Motivo',
                    'affected_document' => 'Doc. afectado',
                    'total' => 'Total',
                    'customer_name' => 'Cliente',
                    'customer_ruc' => 'RUC/DNI',
                ]);
                break;
            case 'payment-method-daily':
                $data = $this->connection->fetchAllAssociative($this->paymentMethodDailySql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_pagos_diarios', [
                    'sale_date' => 'Fecha',
                    'payment_method' => 'Método de pago',
                    'transactions' => 'Transacciones',
                    'total' => 'Total',
                ]);
                break;
            case 'daily-summary':
                $data = $this->connection->fetchAllAssociative($this->dailySummarySql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_resumen_diario', [
                    'sale_date' => 'Fecha',
                    'sales' => 'Ventas',
                    'subtotal' => 'Subtotal',
                    'tax' => 'IGV',
                    'total' => 'Total',
                ]);
                break;
            default:
                throw new BadRequestHttpException('Reporte no encontrado');
        }

        return $reportFile;
    }

    /**
     * @return array<string, string>
     */
    private function buildDateRangeParams(string $localId, string $dateFrom, string $dateTo): array
    {
        $from = new \DateTimeImmutable($dateFrom ?: 'today');
        $to = new \DateTimeImmutable($dateTo ?: 'today');

        return [
            'local_id' => $localId,
            'date_from' => $from->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'date_to' => $to->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        ];
    }

    private function receiptFilter(): string
    {
        if ($this->settings->get(Constants::RECEIPT_REQUIRED)) {
            return 'AND EXISTS (SELECT 1 FROM tax_document td '
                . 'WHERE td.movimiento_id = m.movimiento_id '
                . "AND td.document_type IN ('01','03'))";
        }

        return '';
    }

    private function saleByProductSql(): string
    {
        $receiptFilter = $this->receiptFilter();

        return <<<SQL
select
    coalesce(p.codigo, '-') as product_code,
    coalesce(p.descripcion, 'Sin producto') as product_name,
    c.descripcion as category,
    sum(md.cantidad) as quantity,
    round(sum(md.precio_total) / nullif(sum(md.cantidad),0), 2) as average_unit_price,
    sum(md.precio_total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    inner join movimiento_detalle md on md.movimiento_id = m.movimiento_id
    left join producto p on p.producto_id = md.producto_id
    left join categoria c on c.categoria_id = p.categoria_id
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
    {$receiptFilter}
group by product_code, product_name, category
order by total desc
SQL;
    }

    private function detailedSalesSql(): string
    {
        $receiptFilter = $this->receiptFilter();

        return <<<SQL
select
    date(m.fecha) as sale_date,
    time(m.fecha) as sale_time,
    concat(coalesce(i.series, m.serie), '-', coalesce(i.number, m.numero)) as document,
    coalesce(vt.descripcion, 'N/A') as voucher_type,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc,
    coalesce(pm.name, 'Sin método') as payment_method,
    m.subtotal,
    m.igv as tax,
    m.total,
    m.descuento as discount,
    case
        when m.estado = 2 then 'Anulado'
        else 'Aceptado'
    end as status
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    left join tax_document i on i.movimiento_id = m.movimiento_id and i.document_type in ('01', '03')
    left join tipo_comprobante vt on vt.codigo = i.document_type
    left join cliente c on c.id = m.cliente_id
    left join payment_method pm on pm.id = m.tipo_pago
where
    m.tipo_id = 2
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
    {$receiptFilter}
order by m.fecha desc
SQL;
    }

    private function accountingSalesSql(): string
    {
        return <<<SQL
select
    date(i.issue_date) as issue_date,
    coalesce(vt.descripcion, 'Documento') as voucher_type,
    concat(i.series, '-', i.number) as document,
    coalesce(c.ruc, '-') as customer_ruc,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    i.taxable_amount as taxable_amount,
    i.igv_amount as igv,
    i.rc_amount as service_charge,
    i.total,
    case
        when m.estado = 2 then 'Anulado'
        when i.sunat_status is null or i.sunat_status = '' then 'Pendiente'
        when i.sunat_status = '0' then 'Aceptado'
        when cast(i.sunat_status as unsigned) < 2000 then 'Erroneo'
        when cast(i.sunat_status as unsigned) < 4000 then 'Rechazado'
        else 'Observado'
    end as status
from tax_document i
    inner join movimiento m on m.movimiento_id = i.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
    left join cliente c on c.id = m.cliente_id
    left join tipo_comprobante vt on vt.codigo = i.document_type
where
    m.tipo_id = 2
    and i.document_type in ('01', '03')
    and i.issue_date between :date_from and :date_to
    and a.local_id = :local_id
order by i.issue_date desc
SQL;
    }

    private function voidedSalesSql(): string
    {
        $receiptFilter = $this->receiptFilter();

        return <<<SQL
select
    date(m.fecha) as sale_date,
    concat(coalesce(i.series, m.serie), '-', coalesce(i.number, m.numero)) as document,
    coalesce(vt.descripcion, 'N/A') as voucher_type,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc,
    m.total,
    case
        when m.estado = 2 then 'Anulado'
        else 'Aceptado'
    end as status
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    left join tax_document i on i.movimiento_id = m.movimiento_id and i.document_type in ('01', '03')
    left join cliente c on c.id = m.cliente_id
    left join tipo_comprobante vt on vt.codigo = i.document_type
where
    m.tipo_id = 2
    and m.estado = 2
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
    {$receiptFilter}
order by m.fecha desc
SQL;
    }

    private function creditNotesSql(): string
    {
        return <<<SQL
select
    n.issue_date as issue_date,
    concat(n.series, '-', n.number) as document,
    n.document_type as note_type,
    n.reason_code as reason_code,
    n.reason_description as reason,
    n.affected_document as affected_document,
    n.total,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc
from tax_document n
    inner join movimiento m on m.movimiento_id = n.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
    left join cliente c on c.id = m.cliente_id
where
    n.document_type in ('07', '08')
    and n.issue_date between :date_from and :date_to
    and a.local_id = :local_id
order by n.issue_date desc
SQL;
    }

    private function paymentMethodDailySql(): string
    {
        $receiptFilter = $this->receiptFilter();

        return <<<SQL
select
    date(m.fecha) as sale_date,
    coalesce(pm.name, 'Sin método') as payment_method,
    count(*) as transactions,
    sum(m.total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    left join payment_method pm on pm.id = m.tipo_pago
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
    {$receiptFilter}
group by sale_date, payment_method
order by sale_date desc, payment_method
SQL;
    }

    private function dailySummarySql(): string
    {
        $receiptFilter = $this->receiptFilter();

        return <<<SQL
select
    date(m.fecha) as sale_date,
    count(*) as sales,
    sum(m.subtotal) as subtotal,
    sum(m.total - m.subtotal) as tax,
    sum(m.total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
    {$receiptFilter}
group by sale_date
order by sale_date desc
SQL;
    }
}
