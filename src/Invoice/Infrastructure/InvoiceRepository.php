<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Invoice\InvoiceCalculator;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use App\Sale\Domain\Sale;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

readonly class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection             $connection,
        private DataLoader             $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    /**
     * @throws \Exception
     */
    public function getOne(string $saleId): Invoice
    {
        /** @var ?InvoiceEntity $invoiceEntity */
        $invoiceEntity = $this->entityManager->getRepository(InvoiceEntity::class)->findOneBy(['movimiento_id' => $saleId]);

        if (!$invoiceEntity) {
            throw new \Exception('Invoice not found');
        }

        return DataMapper::map($invoiceEntity, Invoice::class);
    }

    /**
     * @throws \Exception
     */
    public function create(Sale $sale): void
    {
        $localId = $this->connection->fetchOne('select local_id from almacen where almacen_id = ?', [$sale->almacen?->almacen_id]);
        $invoiceCalculator = new InvoiceCalculator(
            floatval($sale->subtotal),
            $sale->descuento ?? 0.0,
            floatval($sale->igv),
            $sale->recargo_consumo ?? 0.0
        );
        $invoiceAmounts = $invoiceCalculator->calculate();
        $this->connection->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?)', [
            $sale->movimiento_id,
            $localId,
            $invoiceAmounts->getSubtotal(),
            $invoiceAmounts->getTaxedAmount(),
            $invoiceAmounts->getDiscountAmount(),
            $invoiceAmounts->getTax(),
            $invoiceAmounts->getTotal()
        ]);
    }

    public function insert(Invoice $invoice): Invoice
    {
        $entity = DataMapper::map($invoice, InvoiceEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Invoice::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(Invoice $invoice): Invoice
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $invoice->comprobante_id);

        if (!$entity) {
            throw new ServiceException('La factura no existe');
        }

        $entity = DataMapper::map($invoice, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Invoice::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws NonUniqueResultException
     * @throws ServiceException
     */
    public function getVoucher(string $saleId): Voucher
    {
        $sql = "
        SELECT
		m.movimiento_id as sale_id,
		IF(LENGTH( c.ruc ) != 11, 1, 6) AS customer_doc_type,
		c.ruc AS customer_doc_number,
		c.razon_social AS customer_name,
		c.domicilio_fiscal AS customer_address,
		c.email AS customer_email,
		e.ruc AS company_id,
		e.razon_social AS company_name,
		e.domicilio_fiscal AS company_address,
		e.telefono AS company_phone,
		IFNULL(i.tipo_comprobante_id, '00') AS doc_type,
		IFNULL(i.serie, m.serie) AS doc_series,
		IFNULL(i.numero, m.numero) AS doc_number,
		IFNULL(i.fecha, m.fecha) AS doc_date,
		'PEN' AS doc_currency,
		IFNULL(i.discount_percent, m.discount_percent) AS doc_discount_percentage,
		IFNULL(i.discount_amount, m.discount_amount) AS doc_discount_amount,
		i.rc as doc_consumption_surcharge_amount,
		ROUND(IFNULL(m.recargo_consumo, 0)/100, 2) as doc_consumption_surcharge_percentage,
		i.gravada AS doc_taxed_amount,
		i.igv AS doc_tax_amount,
		i.igv AS doc_total_taxes,
		i.subtotal AS doc_sale_value,
		IFNULL(i.total, m.gravada) AS doc_sale_amount,
		m.glosa AS doc_note,
		i.digest AS doc_hash,
		CONCAT(c.ruc,'|',i.tipo_comprobante_id,'|',i.serie,'|',i.numero,'|',i.igv,'|',i.total,'|',i.fecha,'|','0','|','0','|',i.digest) as doc_qr_data
	FROM
		vw_movimiento m
		LEFT JOIN comprobante i ON i.movimiento_id = m.movimiento_id
		INNER JOIN almacen a ON a.almacen_id = m.almacen_id
		INNER JOIN `local` l ON l.local_id = a.local_id
		INNER JOIN empresa e ON e.empresa_id = l.empresa_id
		INNER JOIN cliente c ON c.ruc = m.cliente_id
	WHERE
			m.movimiento_id = ?;
        ";

        $rsm = new ResultSetMappingBuilder($this->entityManager);
        $rsm->addScalarResult('sale_id', 'sale_id');
        $rsm->addScalarResult('customer_doc_type', 'customer_doc_type');
        $rsm->addScalarResult('customer_doc_number', 'customer_doc_number');

        $nativeQuery = $this->entityManager->createNativeQuery($sql, $rsm);
        $nativeQuery->setParameter(1, $saleId);
        $result = $nativeQuery->getOneOrNullResult();

        if (!$result) {
            throw new ServiceException('Voucher not found');
        }

        dd($result);

        return DataMapper::map($result, Voucher::class);
    }


}
