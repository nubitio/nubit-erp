<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Invoice\InvoiceCalculator;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Customer\Infrastructure\CustomerEntity;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use App\Sale\Domain\Sale;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    /**
     * @throws \Exception
     */
    public function getOne(string $invoiceId): Invoice
    {
        /** @var ?InvoiceEntity $invoiceEntity */
        $invoiceEntity = $this->entityManager->find(InvoiceEntity::class, $invoiceId);

        if (!$invoiceEntity) {
            throw new \Exception('Invoice not found');
        }

        return DataMapper::map($invoiceEntity, Invoice::class);
    }

    /**
     * @throws \Exception
     */
    public function getOneBySaleId(string $saleId): Invoice
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
        $this->connection->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?,?)', [
            $sale->movimiento_id,
            $localId,
            $invoiceAmounts->getSubtotal(),
            $invoiceAmounts->getTaxedAmount(),
            $invoiceAmounts->getDiscountAmount(),
            $invoiceAmounts->getTax(),
            $invoiceAmounts->getChargesAmount(),
            $invoiceAmounts->getTotal(),
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
     * @throws ORMException
     */
    public function update(Invoice $invoice): Invoice
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $invoice->comprobante_id);

        if (!$entity) {
            throw new ServiceException('La factura no existe');
        }

        $entity = DataMapper::map($invoice, $entity);

        $entity->cliente = $this->entityManager->getReference(CustomerEntity::class, $invoice->cliente?->ruc);
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
     * @throws ServiceException
     * @throws Exception
     */
    public function getVoucher(string $saleId): Voucher
    {
        $voucher = $this->connection->executeQuery('CALL sp_sale_header(?)', [$saleId])->fetchAssociative();
        $items = $this->connection->executeQuery('CALL sp_sale_detail(?)', [$saleId])->fetchAllAssociative();

        if (!$voucher) {
            throw new ServiceException('La venta no existe');
        }

        $voucher['voucher_items'] = $items;

        return DataMapper::map($voucher, Voucher::class);
    }
}
