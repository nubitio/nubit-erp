<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\Options;
use App\Customer\Infrastructure\CustomerEntity;
use App\Invoice\Domain\Invoice;
use App\Invoice\Infrastructure\InvoiceEntity;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\Warehouse\Infrastructure\WarehouseEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class SaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        private Connection             $connection,
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
        private SaleUtils              $saleUtils,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $sale) {
            /** @var Sale $sale */
            $data[] = DataMapper::map($sale, Sale::class);
        }

        return new Paginator($data, $paginator);
    }

    /**
     * @throws Exception
     */
    public function getOneById(string $id): array
    {
        $header = $this->connection->executeQuery('CALL sp_sale_header(?)', [$id])->fetchAssociative();
        $details = $this->connection->executeQuery('CALL sp_sale_detail(?)', [$id])->fetchAllAssociative();

        return [
            'header'  => $header,
            'details' => $details,
        ];
    }

    public function getOne(string $id): ?Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->getRepository(SaleEntity::class)->find($id);

        if (!$entity) {
            return null;
        }

        /** @var Sale $sale */
        $sale = DataMapper::map($entity, Sale::class);
        /** @var ?InvoiceEntity $invoice */
        $invoice = $this->entityManager->getRepository(InvoiceEntity::class)->findOneBy(['movimiento_id' => $sale->movimiento_id]);
        $sale->invoice = $invoice ? DataMapper::map($invoice, Invoice::class) : null;

        return $sale;
    }

    public function insert(Sale $sale): Sale
    {
        $sale = $this->saleUtils->fillSaleValues($sale);
        /** @var SaleEntity $entity */
        $entity = DataMapper::map($sale, SaleEntity::class);
        $warehouse = $this->entityManager->getReference(WarehouseEntity::class, $sale->almacen->almacen_id);
        $entity->almacen = $warehouse;
        $customer = $this->entityManager->getReference(CustomerEntity::class, $sale->cliente->ruc);
        $entity->cliente = $customer;
        $paymentMethod = $this->entityManager->getReference(PaymentMethodEntity::class, $sale->tipo_pago->id);
        $entity->tipo_pago = $paymentMethod;
        foreach ($entity->details as $detail) {
            $detail->movimiento = $entity;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    public function update(Sale $sale): Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity = DataMapper::map($sale, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function void(Sale $sale): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity->estado = 2;
        $entity->glosa = $sale->glosa;
        $entity->updated_by = $sale->updated_by;
        $entity->date_updated = new \DateTime();
        $this->entityManager->flush();
    }
}
