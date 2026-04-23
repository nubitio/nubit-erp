<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\SettingsService;
use App\Core\Service\XlsExporter;
use App\Core\Util\Constants;
use App\Core\Util\DateHelper;
use DateTimeImmutable;
use DateTimeZone;
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
     * Build query parameters with date boundaries converted from Lima (America/Lima, UTC-5) to UTC.
     *
     * The user supplies plain Y-m-d strings that represent Lima calendar dates.
     * We parse them as Lima midnight / end-of-day and convert to UTC so that
     * BETWEEN :date_from AND :date_to correctly covers the full Lima calendar day
     * stored in UTC DATETIME columns.
     *
     * Example: user passes "2026-01-15"
     *   Lima 2026-01-15 00:00:00 → UTC 2026-01-15 05:00:00  (date_from)
     *   Lima 2026-01-15 23:59:59 → UTC 2026-01-16 04:59:59  (date_to)
     *
     * @return array<string, string>
     */
    private function buildDateRangeParams(string $localId, string $dateFrom, string $dateTo): array
    {
        $utcTz = new DateTimeZone('UTC');

        $from = $this->parseLimaDateBoundary($dateFrom ?: null, 0, 0, 0)
            ->setTimezone($utcTz);

        $to = $this->parseLimaDateBoundary($dateTo ?: null, 23, 59, 59)
            ->setTimezone($utcTz);

        return [
            'local_id' => $localId,
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_to' => $to->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse a Y-m-d date string in the Lima timezone and set the given time boundary.
     * If $dateStr is null, defaults to today as seen on the Lima wall-clock calendar.
     *
     * @throws BadRequestHttpException when the string is not a valid Y-m-d date.
     */
    private function parseLimaDateBoundary(?string $dateStr, int $hour, int $minute, int $second): DateTimeImmutable
    {
        $limaTz = new DateTimeZone('America/Lima');

        if ($dateStr === null) {
            // Use Lima's current calendar day as the default.
            return DateHelper::todayLima()->setTime($hour, $minute, $second);
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr, $limaTz);
        if ($date === false) {
            throw new BadRequestHttpException(
                sprintf('Fecha inválida "%s". El formato esperado es YYYY-MM-DD.', $dateStr)
            );
        }

        return $date->setTime($hour, $minute, $second);
    }

    private function receiptFilter(string $salesAlias = 's'): string
    {
        if ($this->settings->get(Constants::RECEIPT_REQUIRED)) {
            return 'AND EXISTS (SELECT 1 FROM tax_document td '
                . "WHERE (td.movimiento_id = {$salesAlias}.legacy_movimiento_id OR td.sale_id = {$salesAlias}.id) "
                . "AND td.document_type IN ('01','03'))";
        }

        return '';
    }

    private function saleByProductSql(): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    coalesce(p.codigo, '-') as product_code,
    coalesce(p.descripcion, 'Sin producto') as product_name,
    c.descripcion as category,
    sum(si.qty) as quantity,
    round(sum(si.net_amount) / nullif(sum(si.qty),0), 2) as average_unit_price,
    sum(si.net_amount) as total
from sales s
    inner join sale_items si on si.sale_id = s.id
    left join producto p on p.producto_id = si.product_id
    left join categoria c on c.categoria_id = p.categoria_id
where
    s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    and s.issued_at between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
group by product_code, product_name, category
order by total desc
SQL;
    }

    private function detailedSalesSql(): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_date,
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
    s.issued_at between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
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

    private function accountingSalesSql(): string
    {
        return <<<SQL
select distinct
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
order by i.issue_date desc
SQL;
    }

    private function voidedSalesSql(): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_date,
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
    left join tax_document i on (i.movimiento_id = s.legacy_movimiento_id OR i.sale_id = s.id) and i.document_type in ('01', '03')
    left join cliente c on c.id = s.customer_id
    left join tipo_comprobante vt on vt.codigo = coalesce(i.document_type, '00')
where
    s.status = 'VOIDED'
    and s.issued_at between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
order by s.issued_at desc
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
    left join cliente c on c.id = n.customer_id
where
    n.document_type in ('07', '08')
    and n.issue_date between :date_from and :date_to
    and n.branch_id = :local_id
order by n.issue_date desc
SQL;
    }

    private function paymentMethodDailySql(): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_date,
    coalesce(pm.name, 'Sin método') as payment_method,
    count(distinct s.id) as transactions,
    sum(sp.amount) as total
from sales s
    inner join sale_payments sp on sp.sale_id = s.id
    left join payment_method pm on pm.id = sp.payment_method_id
where
    s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    and s.issued_at between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
group by sale_date, payment_method
order by sale_date desc, payment_method
SQL;
    }

    private function dailySummarySql(): string
    {
        $receiptFilter = $this->receiptFilter('s');

        return <<<SQL
select
    DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) as sale_date,
    count(*) as sales,
    sum(s.subtotal) as subtotal,
    sum(s.tax_total) as tax,
    sum(s.grand_total) as total
from sales s
where
    s.status in ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
    and s.issued_at between :date_from and :date_to
    and s.branch_id = :local_id
    {$receiptFilter}
group by sale_date
order by sale_date desc
SQL;
    }
}
