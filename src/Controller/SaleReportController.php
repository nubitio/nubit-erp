<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\XlsExporter;
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
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_por_producto');
                break;
            case 'detailed-sales':
                $data = $this->connection->fetchAllAssociative($this->detailedSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_detallado');
                break;
            case 'accounting-report':
                $data = $this->connection->fetchAllAssociative($this->accountingSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_contable');
                break;
            case 'voided-sales':
                $data = $this->connection->fetchAllAssociative($this->voidedSalesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_ventas_anuladas');
                break;
            case 'credit-notes':
                $data = $this->connection->fetchAllAssociative($this->creditNotesSql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_notas_de_credito');
                break;
            case 'payment-method-daily':
                $data = $this->connection->fetchAllAssociative($this->paymentMethodDailySql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_pagos_diarios');
                break;
            case 'daily-summary':
                $data = $this->connection->fetchAllAssociative($this->dailySummarySql(), $params);
                $reportFile = $this->xlsExporter->export($data, 'reporte_resumen_diario');
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

    private function saleByProductSql(): string
    {
        return <<<SQL
select
    coalesce(p.codigo, tdl.product_code, '-') as product_code,
    coalesce(p.descripcion, tdl.product_name) as product_name,
    c.descripcion as category,
    sum(tdl.quantity) as quantity,
    round(sum(tdl.total) / nullif(sum(tdl.quantity),0), 2) as average_unit_price,
    sum(tdl.total) as total
from tax_document td
    inner join movimiento m on m.movimiento_id = td.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
    inner join tax_document_line tdl on tdl.tax_document_id = td.tax_document_id
    left join producto p on p.producto_id = tdl.product_id
    left join categoria c on c.categoria_id = p.categoria_id
where
    td.document_type in ('01', '03')
    m.tipo_id = 2
    and m.estado = 1
    and td.issue_date between :date_from and :date_to
    and a.local_id = :local_id
group by product_code, product_name, category
order by total desc
SQL;
    }

    private function detailedSalesSql(): string
    {
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
    left join cliente c on c.ruc = m.cliente_id
    left join payment_method pm on pm.id = m.tipo_pago
where
    m.tipo_id = 2
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
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
    left join cliente c on c.ruc = m.cliente_id
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
    left join cliente c on c.ruc = m.cliente_id
    left join tipo_comprobante vt on vt.codigo = i.document_type
where
    m.tipo_id = 2
    and m.estado = 2
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
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
    left join cliente c on c.ruc = m.cliente_id
where
    n.document_type in ('07', '08')
    and n.issue_date between :date_from and :date_to
    and a.local_id = :local_id
order by n.issue_date desc
SQL;
    }

    private function paymentMethodDailySql(): string
    {
        return <<<SQL
select
    date(m.fecha) as sale_date,
    coalesce(pm.name, 'Sin método') as payment_method,
    count(*) as transactions,
    sum(m.total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    inner join tax_document td on td.movimiento_id = m.movimiento_id and td.document_type in ('01', '03')
    left join payment_method pm on pm.id = td.payment_method
where
    m.tipo_id = 2
    and m.estado = 1
    and td.issue_date between :date_from and :date_to
    and a.local_id = :local_id
group by sale_date, payment_method
order by sale_date desc, payment_method
SQL;
    }

    private function dailySummarySql(): string
    {
        return <<<SQL
select
    date(td.issue_date) as sale_date,
    count(*) as documents,
    sum(td.subtotal) as subtotal,
    sum(td.igv_amount) as tax,
    sum(td.total) as total
from tax_document td
    inner join movimiento m on m.movimiento_id = td.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
where
    m.tipo_id = 2
    and m.estado = 1
    and td.document_type in ('01', '03')
    and td.issue_date between :date_from and :date_to
    and a.local_id = :local_id
group by sale_date
order by sale_date desc
SQL;
    }
}
