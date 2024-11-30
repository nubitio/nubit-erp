<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Customer\Infrastructure\CustomerEntity;
use App\Operation\Infrastructure\OperationEntity;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;
use App\PriceList\Infrastructure\PriceListEntity;
use App\Product\Infrastructure\ProductEntity;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use App\Supplier\Infrastructure\SupplierEntity;
use App\Warehouse\Infrastructure\WarehouseEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

readonly class SaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private SaleUtils $saleUtils,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    /**
     * @throws NonUniqueResultException
     * @throws ServiceException
     */
    public function getOne(string $id): Sale
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        if ($entity?->numero > 0) {
            $entity = $this->dataLoader->loadOne(new LoadOptions(
                entity: SaleEntity::class,
                mapping: Sale::class,
                joins: [
                    new JoinRule('e.cliente', 'c'),
                    new JoinRule('e.almacen', 'a'),
                    new JoinRule('e.tipo_pago', 'pm'),
                    new JoinRule('e.details', 'sd'),
                    new JoinRule('sd.producto', 'p'),
                ],
                filters: [
                    new FilterRule('movimiento_id', FilterOperation::Equals, $id),
                ],
            ));
        }

        if (!$entity) {
            throw new ServiceException('No se encontro el movimiento de venta');
        }

        /** @var Sale $sale */
        $sale = DataMapper::map($entity, Sale::class);

        return $sale;
    }

    public function insert(Sale $sale): Sale
    {
        if ($sale->tipo_pago) {
            $sale = $this->saleUtils->fillSaleValues($sale);
        }
        $entity = DataMapper::map($sale, SaleEntity::class);
        $warehouse = $this->entityManager->getReference(WarehouseEntity::class, $sale->almacen?->almacen_id);
        $entity->almacen = $warehouse;
        if ($sale->operacion) {
            $operation = $this->entityManager->getReference(OperationEntity::class, $sale->operacion->operacion_id);
            $entity->operacion = $operation;
        }
        if ($sale->proveedor) {
            $supplier = $this->entityManager->getReference(SupplierEntity::class, $sale->proveedor->proveedor_id);
            $entity->proveedor = $supplier;
        }
        if ($sale->cliente) {
            $customer = $this->entityManager->getReference(CustomerEntity::class, $sale->cliente->ruc);
            $entity->cliente = $customer;
        }
        if ($sale->price_list) {
            $priceList = $this->entityManager->getReference(PriceListEntity::class, $sale->price_list->id);
            $entity->price_list = $priceList;
        }
        if ($sale->tipo_pago) {
            $paymentMethod = $this->entityManager->getReference(PaymentMethodEntity::class, $sale->tipo_pago->id);
            $entity->tipo_pago = $paymentMethod;
        }
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
        if ($entity->numero > 0) {
            $entity->details = $details;
        }
        $warehouse = $this->entityManager->getReference(WarehouseEntity::class, $sale->almacen?->almacen_id);
        $entity->almacen = $warehouse;
        if ($sale->operacion) {
            $operation = $this->entityManager->getReference(OperationEntity::class, $sale->operacion->operacion_id);
            $entity->operacion = $operation;
        }
        if ($sale->proveedor) {
            $supplier = $this->entityManager->getReference(SupplierEntity::class, $sale->proveedor->proveedor_id);
            $entity->proveedor = $supplier;
        }
        if ($sale->cliente) {
            $customer = $this->entityManager->getReference(CustomerEntity::class, $sale->cliente->ruc);
            $entity->cliente = $customer;
        }
        if ($sale->price_list) {
            $priceList = $this->entityManager->getReference(PriceListEntity::class, $sale->price_list->id);
            $entity->price_list = $priceList;
        }
        if ($sale->tipo_pago) {
            $paymentMethod = $this->entityManager->getReference(PaymentMethodEntity::class, $sale->tipo_pago->id);
            $entity->tipo_pago = $paymentMethod;
        }
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

    public function getSaleByCustomerId(string $id): PaginatorInterface
    {
        return $this->dataLoader->loadCollection(new LoadOptions(
            entity: SaleEntity::class,
            mapping: Sale::class,
            joins: [
                new JoinRule('e.cliente', 'c'),
                new JoinRule('e.almacen', 'a'),
                new JoinRule('e.tipo_pago', 'pm'),
                new JoinRule('e.details', 'sd'),
                new JoinRule('sd.producto', 'p'),
            ],
            filters: [
                new FilterRule('cliente', FilterOperation::Equals, $id),
            ],
        ));
    }
}
