<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Customer\Infrastructure\CustomerEntity;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;
use App\Product\Infrastructure\ProductEntity;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use App\Warehouse\Infrastructure\WarehouseEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class SaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private SaleUtils $saleUtils,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): Sale
    {
        $entity = $this->entityManager->getRepository(SaleEntity::class)->find($id);

        if (!$entity) {
            throw new ServiceException('No se encontro el movimiento de venta');
        }

        /** @var Sale $sale */
        $sale = DataMapper::map($entity, Sale::class);

        return $sale;
    }

    public function insert(Sale $sale): Sale
    {
        $sale = $this->saleUtils->fillSaleValues($sale);
        $entity = DataMapper::map($sale, SaleEntity::class);
        $warehouse = $this->entityManager->getReference(WarehouseEntity::class, $sale->almacen?->almacen_id);
        $entity->almacen = $warehouse;
        $customer = $this->entityManager->getReference(CustomerEntity::class, $sale->cliente?->ruc);
        $entity->cliente = $customer;
        $paymentMethod = $this->entityManager->getReference(PaymentMethodEntity::class, $sale->tipo_pago?->id);
        $entity->tipo_pago = $paymentMethod;
        $subtotal = 0;
        /** @var SaleDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->movimiento = $entity;
            $detail->producto = $this->entityManager->getReference(ProductEntity::class, $detail->producto?->producto_id);
            $subtotal += $detail->precio_total;
        }
        $entity->subtotal = $subtotal;
        $entity->total = $subtotal - ($subtotal * $entity->descuento / 100);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    public function update(Sale $sale): Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $details = $entity->details;
        $entity = DataMapper::map($sale, $entity);
        $entity->details = $details;
        $warehouse = $this->entityManager->getReference(WarehouseEntity::class, $sale->almacen?->almacen_id);
        $entity->almacen = $warehouse;
        $customer = $this->entityManager->getReference(CustomerEntity::class, $sale->cliente?->ruc);
        $entity->cliente = $customer;
        $paymentMethod = $this->entityManager->getReference(PaymentMethodEntity::class, $sale->tipo_pago?->id);
        $entity->tipo_pago = $paymentMethod;
        $subtotal = 0;
        /** @var SaleDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->movimiento = $entity;
            $detail->producto = $this->entityManager->getReference(ProductEntity::class, $detail->producto?->producto_id);
            $subtotal += $detail->precio_total;
        }
        $entity->subtotal = $subtotal;
        $entity->total = $subtotal - ($subtotal * $entity->descuento / 100);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function void(Sale $sale): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);

        if (!$entity) {
            throw new ServiceException('No se encontró el movimiento de venta');
        }

        $entity->estado = 2;
        $entity->glosa = $sale->glosa;
        $entity->updated_by = $sale->updated_by;
        $entity->date_updated = new \DateTime();
        $this->entityManager->flush();
    }
}
