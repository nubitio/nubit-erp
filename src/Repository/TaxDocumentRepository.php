<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Exception\ServiceException;
use App\Dto\Invoice\VoucherHeaderDto;
use App\Dto\Invoice\VoucherLineDto;
use App\Dto\TaxDocument\SeriesResultDto;
use App\Entity\TaxDocument;
use App\Invoice\Domain\Voucher;
use App\Invoice\Domain\VoucherItem;
use App\Sale\Domain\DocType;
use App\Service\InvoiceCalculator;
use App\Service\InvoiceLineAmounts;
use App\Service\InvoiceLineInput;
use Brick\Math\Exception\MathException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocument>
 */
class TaxDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxDocument::class);
    }

    public function getTaxDocumentSeriesByBranch(string $branchId, string $documentType): ?SeriesResultDto
    {
        $sql = <<<SQL
SELECT
    serie,
    serie_asociada
FROM local_comprobante
WHERE local_id = ?
  AND tipo_comprobante_id = ?
SQL;

        $result = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                $sql,
                [$branchId, $documentType]
            )->fetchAssociative();

        return $result ? new SeriesResultDto(
            series: $result['serie'],
            associatedSeries: $result['serie_asociada']
        ) : null;
    }

    public function getNextSequenceNumber(string $branchId, string $documentType): int
    {
        $sql = <<<SQL
SELECT
    MAX(number) + 1 AS next_number
FROM tax_document
WHERE branch_id = ?
AND document_type = ?
ORDER BY next_number DESC
LIMIT 1
SQL;

        $nextNumber = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                $sql,
                [$branchId, $documentType]
            )->fetchAssociative()['next_number'];

        if ($nextNumber) {
            return (int)$nextNumber;
        } else {
            return 1;
        }
    }

    /**
     * @throws Exception|MathException
     */
    public function getVoucher(string $saleId): Voucher
    {
        $connection = $this->getEntityManager()->getConnection();
        $voucher = $connection
            ->executeQuery($this->saleHeaderQuery(), ['saleId' => $saleId])
            ->fetchAssociative();
        $items = $connection
            ->executeQuery($this->saleDetailQuery(), ['saleId' => $saleId])
            ->fetchAllAssociative();

        if (!$voucher) {
            throw new ServiceException('La venta no existe');
        }

        $header = VoucherHeaderDto::fromArray($voucher);
        $lines = array_map(static fn (array $row): VoucherLineDto => VoucherLineDto::fromArray($row), $items);

        // Nota de venta must preserve persisted line totals
        if ($header->voucherType === DocType::NOTA_VENTA->value) {
            $voucherItems = array_map(
                static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
                $lines
            );

            return $header->toVoucher($voucherItems);
        }

        $calculator = new InvoiceCalculator(
            array_map(
                static fn (VoucherLineDto $line): InvoiceLineInput => new InvoiceLineInput(
                    quantity: $line->quantity ?? '0',
                    unitPrice: $line->unitPrice ?? '0'
                ),
                $lines
            ),
            $header->voucherDiscountPercentage ?? '0',
            $header->voucherTaxPercentage ?? '0',
            $header->voucherConsumptionSurchargePercentage ?? '0',
            $header->voucherSaleAmount ?? null
        );

        $amounts = $calculator->calculate();
        $amountLines = $amounts->getLines();

        foreach ($lines as $index => $line) {
            $amountLine = $amountLines[$index] ?? null;
            if ($amountLine instanceof InvoiceLineAmounts) {
                $line->applyAmounts($amountLine);
            }
        }

        $header->applyAmounts($amounts);

        $voucherItems = array_map(
            static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
            $lines
        );

        return $header->toVoucher($voucherItems);
    }

    /**
     * @throws Exception|MathException
     */
    public function getNoteVoucher(string $taxDocumentId): Voucher
    {
        $connection = $this->getEntityManager()->getConnection();
        $voucher = $connection
            ->executeQuery($this->noteHeaderQuery(), ['taxDocumentId' => $taxDocumentId])
            ->fetchAssociative();
        $items = $connection
            ->executeQuery($this->noteDetailQuery(), ['taxDocumentId' => $taxDocumentId])
            ->fetchAllAssociative();

        if (!$voucher) {
            throw new ServiceException('La nota de crédito no existe');
        }

        $header = VoucherHeaderDto::fromArray($voucher);
        $lines = array_map(static fn (array $row): VoucherLineDto => VoucherLineDto::fromArray($row), $items);

        $calculator = new InvoiceCalculator(
            array_map(
                static fn (VoucherLineDto $line): InvoiceLineInput => new InvoiceLineInput(
                    quantity: $line->quantity ?? '0',
                    unitPrice: $line->unitPrice ?? '0'
                ),
                $lines
            ),
            $header->voucherDiscountPercentage ?? '0',
            $header->voucherTaxPercentage ?? '0',
            $header->voucherConsumptionSurchargePercentage ?? '0',
            $header->voucherSaleAmount ?? null
        );

        $amounts = $calculator->calculate();
        $amountLines = $amounts->getLines();

        foreach ($lines as $index => $line) {
            $amountLine = $amountLines[$index] ?? null;
            if ($amountLine instanceof InvoiceLineAmounts) {
                $line->applyAmounts($amountLine);
            }
        }

        $header->applyAmounts($amounts);

        $voucherItems = array_map(
            static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
            $lines
        );

        return $header->toVoucher($voucherItems);
    }

    private function saleHeaderQuery(): string
    {
        return <<<SQL
SELECT
    m.movimiento_id AS sale_id,
    CASE WHEN LENGTH(c.ruc) != 11 THEN 1 ELSE 6 END AS customer_doc_type,
    c.ruc AS customer_doc_number,
    c.razon_social AS customer_name,
    c.domicilio_fiscal AS customer_address,
    c.email AS customer_email,
    c.telefono AS customer_phone,
    e.ruc AS company_id,
    e.razon_social AS company_name,
    e.domicilio_fiscal AS company_address,
    e.telefono AS company_phone,
    l.direccion AS branch_address,
    l.telefono AS branch_phone,
    IFNULL(i.document_type, '00') AS voucher_type,
    IFNULL(i.series, m.serie) AS voucher_series,
    IFNULL(i.number, m.numero) AS voucher_number,
    m.forma_pago AS voucher_payment_method,
    IFNULL(i.issue_date, m.fecha) AS voucher_date,
    IFNULL(i.currency, 'PEN') AS voucher_currency,
    IFNULL(i.discount_percent, m.descuento) AS voucher_discount_percentage,
    IFNULL(i.discount_amount, detail.discount_amount) AS voucher_discount_amount,
    i.rc_amount as voucher_consumption_surcharge_amount,
    IFNULL(m.recargo_consumo, 0) as voucher_consumption_surcharge_percentage,
    IFNULL(i.taxable_amount, detail.taxed_amount) AS voucher_taxed_amount,
    IFNULL(i.igv_amount, detail.tax_amount) AS voucher_tax_amount,
    m.igv AS voucher_tax_percentage,
    IFNULL(i.igv_amount, detail.tax_amount) AS voucher_total_taxes,
    IFNULL(i.subtotal, detail.taxed_amount) AS voucher_sale_value,
    IFNULL(i.total, detail.sale_amount) AS voucher_sale_amount,
    IFNULL(i.operation_type, m.tipo_operacion) AS voucher_operation_type,
    IFNULL(i.detraction_code, m.detraccion_codigo) AS voucher_detraction_code,
    IFNULL(i.detraction_percent, m.detraccion_porcentaje) AS voucher_detraction_percent,
    IFNULL(i.detraction_amount, m.detraccion_monto) AS voucher_detraction_amount,
    IFNULL(i.detraction_payment_method, m.detraccion_medio_pago) AS voucher_detraction_payment_method,
    sc_pay.description AS voucher_detraction_payment_description,
    IFNULL(i.detraction_value_ref, m.detraccion_valor_referencial) AS voucher_detraction_value_ref,
    e.detraccion_account AS voucher_detraction_account,
    sc_det.description AS voucher_detraction_description,
    e.bank_name AS voucher_bank_name,
    e.bank_account AS voucher_bank_account,
    e.bank_cci AS voucher_bank_cci,
    m.glosa AS voucher_note,
    i.created_at AS date_comprobante,
    i.digest AS voucher_hash,
    m.estado AS voucher_status,
    CONCAT(
        c.ruc,
        '|',
        i.document_type,
        '|',
        i.series,
        '|',
        i.number,
        '|',
        i.igv_amount,
        '|',
        i.total,
        '|',
        i.issue_date,
        '|',
        '0',
        '|',
        '0',
        '|',
        IFNULL(i.digest, '')
    ) as voucher_qr_data
FROM
    movimiento m
    LEFT JOIN tax_document i
        ON i.movimiento_id = m.movimiento_id
       AND i.document_type IN ('01', '03')
    INNER JOIN almacen a ON a.almacen_id = m.almacen_id
    INNER JOIN `local` l ON l.local_id = a.local_id
    INNER JOIN empresa e ON e.empresa_id = l.empresa_id
    INNER JOIN cliente c ON c.ruc = m.cliente_id
    LEFT JOIN sunat_catalog sc_det
        ON sc_det.catalog = 54
        AND sc_det.code = IFNULL(i.detraction_code, m.detraccion_codigo)
    LEFT JOIN sunat_catalog sc_pay
        ON sc_pay.catalog = 59
        AND sc_pay.code = IFNULL(i.detraction_payment_method, m.detraccion_medio_pago)
    LEFT JOIN (
        SELECT
            md.movimiento_id,
            SUM(
                ROUND(md.precio_unitario / (1 + (m2.igv / 100 + m2.recargo_consumo / 100)), 6) * md.cantidad
            ) AS taxed_amount,
            SUM(
                ROUND(
                    (
                        ROUND(md.precio_unitario / (1 + (m2.igv / 100 + m2.recargo_consumo / 100)), 6)
                        * m2.igv / 100
                    )
                    * md.cantidad,
                    2
                )
            ) AS tax_amount,
            SUM(
                ROUND(md.precio_total - md.precio_total * IFNULL(m2.descuento, 0) / 100, 2)
            ) AS sale_amount,
            SUM(ROUND(md.precio_total * IFNULL(m2.descuento, 0) / 100, 2)) AS discount_amount
        FROM movimiento m2
        JOIN movimiento_detalle md ON md.movimiento_id = m2.movimiento_id
        WHERE m2.movimiento_id = :saleId
        GROUP BY md.movimiento_id
    ) detail ON detail.movimiento_id = m.movimiento_id
WHERE
    m.movimiento_id = :saleId
SQL;
    }

    private function noteHeaderQuery(): string
    {
        return <<<SQL
SELECT
    m.movimiento_id AS sale_id,
    CASE WHEN LENGTH(c.ruc) != 11 THEN 1 ELSE 6 END AS customer_doc_type,
    c.ruc AS customer_doc_number,
    c.razon_social AS customer_name,
    c.domicilio_fiscal AS customer_address,
    c.email AS customer_email,
    c.telefono AS customer_phone,
    e.ruc AS company_id,
    e.razon_social AS company_name,
    e.domicilio_fiscal AS company_address,
    e.telefono AS company_phone,
    l.direccion AS branch_address,
    l.telefono AS branch_phone,
    td.document_type AS voucher_type,
    td.series AS voucher_series,
    td.number AS voucher_number,
    IFNULL(cn.payment_method, m.forma_pago) AS voucher_payment_method,
    cn.issue_date AS voucher_date,
    IFNULL(cn.currency, 'PEN') AS voucher_currency,
    IFNULL(cn.discount_percent, 0) AS voucher_discount_percentage,
    IFNULL(cn.discount_amount, 0) AS voucher_discount_amount,
    IFNULL(cn.rc_amount, 0) AS voucher_consumption_surcharge_amount,
    IFNULL(m.recargo_consumo, 0) AS voucher_consumption_surcharge_percentage,
    IFNULL(cn.taxable_amount, 0) AS voucher_taxed_amount,
    IFNULL(cn.igv_amount, 0) AS voucher_tax_amount,
    IFNULL(m.igv, 0) AS voucher_tax_percentage,
    IFNULL(cn.igv_amount, 0) AS voucher_total_taxes,
    IFNULL(cn.subtotal, 0) AS voucher_sale_value,
    IFNULL(cn.total, 0) AS voucher_sale_amount,
    IFNULL(cn.operation_type, m.tipo_operacion) AS voucher_operation_type,
    IFNULL(cn.detraction_code, m.detraccion_codigo) AS voucher_detraction_code,
    IFNULL(cn.detraction_percent, m.detraccion_porcentaje) AS voucher_detraction_percent,
    IFNULL(cn.detraction_amount, m.detraccion_monto) AS voucher_detraction_amount,
    IFNULL(cn.detraction_payment_method, m.detraccion_medio_pago) AS voucher_detraction_payment_method,
    sc_pay.description AS voucher_detraction_payment_description,
    IFNULL(cn.detraction_value_ref, m.detraccion_valor_referencial) AS voucher_detraction_value_ref,
    e.detraccion_account AS voucher_detraction_account,
    sc_det.description AS voucher_detraction_description,
    e.bank_name AS voucher_bank_name,
    e.bank_account AS voucher_bank_account,
    e.bank_cci AS voucher_bank_cci,
    m.glosa AS voucher_note,
    cn.created_at AS date_comprobante,
    cn.digest AS voucher_hash,
    m.estado AS voucher_status,
    CONCAT(
        c.ruc,
        '|',
        td.document_type,
        '|',
        td.series,
        '|',
        td.number,
        '|',
        cn.igv_amount,
        '|',
        cn.total,
        '|',
        cn.issue_date,
        '|',
        '0',
        '|',
        '0',
        '|',
        IFNULL(cn.digest, '')
    ) as voucher_qr_data
FROM tax_document cn
INNER JOIN tax_document td ON td.tax_document_id = cn.related_tax_document_id
INNER JOIN movimiento m ON m.movimiento_id = cn.movimiento_id
INNER JOIN almacen a ON a.almacen_id = m.almacen_id
INNER JOIN `local` l ON l.local_id = a.local_id
INNER JOIN empresa e ON e.empresa_id = l.empresa_id
INNER JOIN cliente c ON c.ruc = m.cliente_id
LEFT JOIN sunat_catalog sc_det
    ON sc_det.catalog = 54
    AND sc_det.code = IFNULL(cn.detraction_code, m.detraccion_codigo)
LEFT JOIN sunat_catalog sc_pay
    ON sc_pay.catalog = 59
    AND sc_pay.code = IFNULL(cn.detraction_payment_method, m.detraccion_medio_pago)
WHERE cn.tax_document_id = :taxDocumentId
LIMIT 1
SQL;
    }

    private function noteDetailQuery(): string
    {
        return <<<SQL
SELECT
    tdl.id AS line_id,
    tdl.product_id AS product_id,
    tdl.product_code AS product_code,
    tdl.product_uom AS product_uom,
    tdl.product_name AS product_name,
    tdl.quantity AS quantity,
    tdl.unit_price AS unit_price,
    tdl.unit_value AS unit_value,
    tdl.discount_amount AS discount_amount,
    tdl.taxed_amount AS taxed_amount,
    tdl.tax_amount AS tax_amount,
    tdl.tax_percentage AS tax_percentage,
    tdl.total AS total,
    IFNULL(tdl.tax_type, '10') AS tax_type
FROM tax_document_line tdl
WHERE tdl.tax_document_id = :taxDocumentId
ORDER BY tdl.line_number ASC
SQL;
    }

    private function saleDetailQuery(): string
    {
        return <<<SQL
SELECT
    line_id,
    product_id,
    product_code,
    product_uom,
    product_name,
    quantity,
    unit_price,
    tax_percentage,
    total,
    tax_type
FROM (
    SELECT
        tdl.id AS line_id,
        tdl.product_id AS product_id,
        tdl.product_code AS product_code,
        tdl.product_uom AS product_uom,
        tdl.product_name AS product_name,
        tdl.quantity AS quantity,
        tdl.unit_price AS unit_price,
        m.igv AS tax_percentage,
        tdl.total AS total,
        IFNULL(tdl.tax_type, '10') AS tax_type
    FROM tax_document td
    INNER JOIN tax_document_line tdl ON tdl.tax_document_id = td.tax_document_id
    INNER JOIN movimiento m ON m.movimiento_id = td.movimiento_id
    WHERE td.movimiento_id = :saleId
      AND td.document_type IN ('01', '03')

    UNION ALL

    SELECT
        md.id AS line_id,
        p.producto_id AS product_id,
        p.codigo AS product_code,
        u.`code` AS product_uom,
        p.descripcion AS product_name,
        md.cantidad AS quantity,
        md.precio_unitario AS unit_price,
        m.igv AS tax_percentage,
        md.precio_total AS total,
        '10' AS tax_type
    FROM movimiento m
    INNER JOIN movimiento_detalle md ON md.movimiento_id = m.movimiento_id
    INNER JOIN producto p ON p.producto_id = md.producto_id
    LEFT JOIN unidad u ON u.unidad_id = p.unidad_id
    WHERE m.movimiento_id = :saleId
      AND NOT EXISTS (
          SELECT 1
          FROM tax_document td2
          WHERE td2.movimiento_id = m.movimiento_id
            AND td2.document_type IN ('01', '03')
      )
) AS sale_lines
ORDER BY line_id ASC
SQL;
    }

    /**
     * @return array<string, mixed>|null
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    public function findTaxDocumentBySearchCriteria(
        string $companyRuc,
        string $voucherType,
        string $series,
        string $number,
        string $date,
        string $total,
    ): ?array {
        $dateStart = new \DateTimeImmutable($date . ' 00:00:00')->format('Y-m-d H:i:s');
        $dateEnd = new \DateTimeImmutable($date . ' 23:59:59')->format('Y-m-d H:i:s');
        $normalizedNumber = ltrim($number, '0');
        $normalizedNumber = $normalizedNumber === '' ? '0' : $normalizedNumber;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<SQL
SELECT
    td.tax_document_id,
    td.movimiento_id,
    td.document_type,
    td.series,
    td.number,
    td.issue_date,
    td.total,
    td.digest,
    td.sunat_status,
    m.estado AS sale_status
FROM tax_document td
INNER JOIN movimiento m ON m.movimiento_id = td.movimiento_id
INNER JOIN almacen a ON a.almacen_id = m.almacen_id
INNER JOIN `local` l ON l.local_id = a.local_id
INNER JOIN empresa e ON e.empresa_id = l.empresa_id
WHERE e.ruc = :companyRuc
  AND td.document_type = :voucherType
  AND td.series = :series
  AND CAST(td.number AS UNSIGNED) = CAST(:number AS UNSIGNED)
  AND td.issue_date BETWEEN :dateStart AND :dateEnd
  AND td.total = :total
LIMIT 1
SQL,
            [
                'companyRuc' => $companyRuc,
                'voucherType' => $voucherType,
                'series' => strtoupper($series),
                'number' => $normalizedNumber,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'total' => number_format((float)$total, 2, '.', ''),
            ]
        );

        return $row === false ? null : $row;
    }
}
