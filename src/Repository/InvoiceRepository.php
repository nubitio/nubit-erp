<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Exception\ServiceException;
use App\Entity\Invoice;
use App\Invoice\Domain\Voucher;
use App\Invoice\Domain\VoucherItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DenormalizerInterface $denormalizer
    ) {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
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

        $context = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];

        $voucher['voucher_items'] = array_map(
            fn (array $item): mixed => $this->denormalizer->denormalize(
                $item,
                VoucherItem::class,
                null,
                $context
            ),
            $items
        );

        return $this->denormalizer->denormalize($voucher, Voucher::class, null, $context);
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
    IFNULL(i.tipo_comprobante_id, '00') AS voucher_type,
    IFNULL(i.serie, m.serie) AS voucher_series,
    IFNULL(i.numero, m.numero) AS voucher_number,
    m.forma_pago AS voucher_payment_method,
    IFNULL(i.fecha, m.fecha) AS voucher_date,
    'PEN' AS voucher_currency,
    IFNULL(i.discount_percent, m.descuento) AS voucher_discount_percentage,
    IFNULL(i.discount_amount, detail.discount_amount) AS voucher_discount_amount,
    i.rc as voucher_consumption_surcharge_amount,
    IFNULL(m.recargo_consumo, 0) as voucher_consumption_surcharge_percentage,
    IFNULL(i.gravada, detail.taxed_amount) AS voucher_taxed_amount,
    IFNULL(i.igv, detail.tax_amount) AS voucher_tax_amount,
    m.igv AS voucher_tax_percentage,
    IFNULL(i.igv, detail.tax_amount) AS voucher_total_taxes,
    IFNULL(i.subtotal, detail.taxed_amount) AS voucher_sale_value,
    IFNULL(i.total, detail.sale_amount) AS voucher_sale_amount,
    m.glosa AS voucher_note,
    i.created_at AS date_comprobante,
    i.digest AS voucher_hash,
    IFNULL(i.voided, m.estado) AS voucher_status,
    CONCAT(
        c.ruc,
        '|',
        i.tipo_comprobante_id,
        '|',
        i.serie,
        '|',
        i.numero,
        '|',
        i.igv,
        '|',
        i.total,
        '|',
        i.fecha,
        '|',
        '0',
        '|',
        '0',
        '|',
        IFNULL(i.digest, '')
    ) as voucher_qr_data
FROM
    movimiento m
    LEFT JOIN comprobante i ON i.movimiento_id = m.movimiento_id
    INNER JOIN almacen a ON a.almacen_id = m.almacen_id
    INNER JOIN `local` l ON l.local_id = a.local_id
    INNER JOIN empresa e ON e.empresa_id = l.empresa_id
    INNER JOIN cliente c ON c.ruc = m.cliente_id
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

    private function saleDetailQuery(): string
    {
        return <<<SQL
SELECT
    p.producto_id AS product_id,
    p.codigo AS product_code,
    u.`code` AS product_uom,
    p.descripcion AS product_name,
    md.cantidad AS quantity,
    ROUND(md.precio_unitario, 2) AS price,
    ROUND(md.precio_unitario / (1 + (m.igv / 100 + m.recargo_consumo / 100)), 6) AS unit_value,
    ROUND((md.precio_unitario / (1 + (m.igv / 100 + m.recargo_consumo / 100))) * md.cantidad, 2) AS taxed_amount,
    ROUND(
        (md.precio_unitario / (1 + (m.igv / 100 + m.recargo_consumo / 100)) * m.igv / 100) * md.cantidad,
        2
    ) AS tax_amount,
    ROUND(
        md.precio_unitario / (1 + (m.igv / 100 + m.recargo_consumo / 100)) +
        ((md.precio_unitario / (1 + (m.igv / 100 + m.recargo_consumo / 100))) * m.igv / 100),
        2
    ) AS unit_price,
    m.igv AS tax_percentage,
    ROUND(md.precio_total, 2) AS total,
    '10' AS tax_type
FROM movimiento m
INNER JOIN movimiento_detalle md ON md.movimiento_id = m.movimiento_id
INNER JOIN producto p ON p.producto_id = md.producto_id
LEFT JOIN unidad u ON u.unidad_id = p.unidad_id
WHERE m.movimiento_id = :saleId
SQL;
    }
}
