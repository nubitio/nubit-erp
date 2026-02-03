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
    p.codigo as product_code,
    p.descripcion as product_name,
    c.descripcion as category,
    sum(md.cantidad) as quantity,
    round(sum(md.precio_total) / nullif(sum(md.cantidad),0), 2) as average_unit_price,
    sum(md.precio_total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    inner join movimiento_detalle md on md.movimiento_id = m.movimiento_id
    inner join producto p on p.producto_id = md.producto_id
    left join categoria c on c.categoria_id = p.categoria_id
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
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
    concat(coalesce(i.serie, m.serie), '-', coalesce(i.numero, m.numero)) as document,
    coalesce(vt.descripcion, 'N/A') as voucher_type,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc,
    coalesce(pm.name, 'Sin método') as payment_method,
    m.subtotal,
    m.igv as tax,
    m.total,
    m.descuento as discount,
    case
        when coalesce(i.voided, m.estado) = 2 then 'Anulado'
        else 'Vigente'
    end as status
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    left join comprobante i on i.movimiento_id = m.movimiento_id
    left join tipo_comprobante vt on vt.codigo = i.tipo_comprobante_id
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
    date(i.fecha) as issue_date,
    coalesce(vt.descripcion, 'Documento') as voucher_type,
    concat(i.serie, '-', i.numero) as document,
    coalesce(c.ruc, '-') as customer_ruc,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    i.gravada as taxable_amount,
    i.igv,
    i.rc as service_charge,
    i.total,
    case
        when i.voided = 2 then 'Anulado'
        when i.envio_sunat is null or i.envio_sunat = '' then 'Pendiente'
        when cast(i.envio_sunat as unsigned) = 0 then 'Aceptado'
        when cast(i.envio_sunat as unsigned) < 2000 then 'Erroneo'
        when cast(i.envio_sunat as unsigned) < 4000 then 'Rechazado'
        else 'Observado'
    end as status
from comprobante i
    inner join movimiento m on m.movimiento_id = i.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
    left join cliente c on c.ruc = m.cliente_id
    left join tipo_comprobante vt on vt.codigo = i.tipo_comprobante_id
where
    m.tipo_id = 2
    and i.fecha between :date_from and :date_to
    and a.local_id = :local_id
order by i.fecha desc
SQL;
    }

    private function voidedSalesSql(): string
    {
        return <<<SQL
select
    date(m.fecha) as sale_date,
    concat(coalesce(i.serie, m.serie), '-', coalesce(i.numero, m.numero)) as document,
    coalesce(vt.descripcion, 'N/A') as voucher_type,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc,
    m.total,
    case
        when coalesce(i.voided, m.estado) = 2 then 'Anulado'
        else 'Vigente'
    end as status
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
    left join comprobante i on i.movimiento_id = m.movimiento_id
    left join cliente c on c.ruc = m.cliente_id
    left join tipo_comprobante vt on vt.codigo = i.tipo_comprobante_id
where
    m.tipo_id = 2
    and (coalesce(i.voided, 0) = 1 or m.estado = 0)
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
order by m.fecha desc
SQL;
    }

    private function creditNotesSql(): string
    {
        return <<<SQL
select
    n.fecha as issue_date,
    concat(n.serie, '-', n.correlativo) as document,
    n.tipo_id as note_type,
    n.codigo_motivo as reason_code,
    n.descripcion_motivo as reason,
    n.doc_afectado as affected_document,
    n.total,
    coalesce(c.razon_social, 'Sin cliente') as customer_name,
    coalesce(c.ruc, '-') as customer_ruc
from nota n
    inner join movimiento m on m.movimiento_id = n.movimiento_id
    inner join almacen a on a.almacen_id = m.almacen_id
    left join cliente c on c.ruc = m.cliente_id
where
    n.fecha between :date_from and :date_to
    and a.local_id = :local_id
order by n.fecha desc
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
    left join payment_method pm on pm.id = m.tipo_pago
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
group by sale_date, payment_method
order by sale_date desc, payment_method
SQL;
    }

    private function dailySummarySql(): string
    {
        return <<<SQL
select
    date(m.fecha) as sale_date,
    count(*) as documents,
    sum(m.subtotal) as subtotal,
    sum(m.igv) as tax,
    sum(m.total) as total
from movimiento m
    inner join almacen a on a.almacen_id = m.almacen_id
where
    m.tipo_id = 2
    and m.estado = 1
    and m.fecha between :date_from and :date_to
    and a.local_id = :local_id
group by sale_date
order by sale_date desc
SQL;
    }
}
