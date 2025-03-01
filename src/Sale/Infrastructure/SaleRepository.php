<?php

declare(strict_types=1);

namespace App\Sale\Infrastructure;

use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\PriceList;
use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
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

    #[\Override]
    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    /**
     * @throws NonUniqueResultException
     * @throws ServiceException
     */
    #[\Override]
    public function getOne(string $id): Sale
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        if ($entity?->numero > 0) {
            $entity = $this->dataLoader->loadOne(
                new LoadOptions(
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
                        new FilterRule(
                            'movimiento_id',
                            FilterOperation::Equals,
                            $id
                        ),
                    ],
                )
            );
        }

        if (!$entity) {
            throw new ServiceException('No se encontro el movimiento de venta');
        }

        /** @var Sale $sale */
        $sale = DataMapper::map($entity, Sale::class);

        return $sale;
    }

    #[\Override]
    public function insert(Sale $sale): Sale
    {
        if ($sale->tipo_pago instanceof \App\Entity\PaymentMethod) {
            $sale = $this->saleUtils->fillSaleValues($sale);
        }

        $entity = DataMapper::map($sale, SaleEntity::class);
        $warehouse = $this->entityManager->getReference(
            Warehouse::class,
            $sale->almacen?->getId()
        );
        $entity->almacen = $warehouse;
        if ($sale->operacion instanceof \App\Entity\LogisticOperation) {
            $operation = $this->entityManager->getReference(
                LogisticOperation::class,
                $sale->operacion->getId()
            );
            $entity->operacion = $operation;
        }

        if ($sale->proveedor instanceof \App\Entity\Supplier) {
            $supplier = $this->entityManager->getReference(
                Supplier::class,
                $sale->proveedor->getId()
            );
            $entity->proveedor = $supplier;
        }

        if ($sale->cliente instanceof \App\Entity\Customer) {
            $customer = $this->entityManager->getReference(
                Customer::class,
                $sale->cliente->getRuc()
            );
            $entity->cliente = $customer;
        }

        if ($sale->price_list instanceof \App\Entity\PriceList) {
            $priceList = $this->entityManager->getReference(
                PriceList::class,
                $sale->price_list->getId()
            );
            $entity->price_list = $priceList;
        }

        if ($sale->tipo_pago instanceof \App\Entity\PaymentMethod) {
            $paymentMethod = $this->entityManager->getReference(
                PaymentMethod::class,
                $sale->tipo_pago->getId()
            );
            $entity->tipo_pago = $paymentMethod;
        }

        $subtotal = 0;
        /** @var SaleDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->movimiento = $entity;
            $product = $this->entityManager->getRepository(
                Product::class
            )->find($detail->producto->getId());
            $detail->producto = $product;
            $subtotal += $detail->precio_total;
            $currentStock = $product->getStock();
            if (1 === $sale->tipo_id) {
                $product->setStock($currentStock + $detail->cantidad);
            } else {
                $product->setStock($currentStock - $detail->cantidad);
            }

            $this->entityManager->persist($product);
        }

        $entity->subtotal = $subtotal;
        $entity->total = $subtotal - ($subtotal * $entity->descuento / 100);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    #[\Override]
    public function update(Sale $sale): Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->find(
            Sale::class,
            $sale->movimiento_id
        );
        $details = $entity->details;
        $entity = DataMapper::map($sale, $entity);
        if ($entity->numero > 0) {
            $entity->details = $details;
        }

        $warehouse = $this->entityManager->getReference(
            Warehouse::class,
            $sale->almacen?->getId()
        );
        $entity->almacen = $warehouse;
        if ($sale->operacion instanceof \App\Entity\LogisticOperation) {
            $operation = $this->entityManager->getReference(
                LogisticOperation::class,
                $sale->operacion->getId()
            );
            $entity->operacion = $operation;
        }

        if ($sale->proveedor instanceof \App\Entity\Supplier) {
            $supplier = $this->entityManager->getReference(
                Supplier::class,
                $sale->proveedor->getId()
            );
            $entity->proveedor = $supplier;
        }

        if ($sale->cliente instanceof \App\Entity\Customer) {
            $customer = $this->entityManager->getReference(
                Customer::class,
                $sale->cliente->getRuc()
            );
            $entity->cliente = $customer;
        }

        if ($sale->price_list instanceof \App\Entity\PriceList) {
            $priceList = $this->entityManager->getReference(
                PriceList::class,
                $sale->price_list->getId()
            );
            $entity->price_list = $priceList;
        }

        if ($sale->tipo_pago instanceof \App\Entity\PaymentMethod) {
            $paymentMethod = $this->entityManager->getReference(
                PaymentMethod::class,
                $sale->tipo_pago->getId()
            );
            $entity->tipo_pago = $paymentMethod;
        }

        $subtotal = 0;
        /** @var SaleDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->movimiento = $entity;
            $detail->producto = $this->entityManager->getReference(
                Product::class,
                $detail->producto?->getId()
            );
            $subtotal += $detail->precio_total;
        }

        $entity->subtotal = $subtotal;
        $entity->total = $subtotal - ($subtotal * $entity->descuento / 100);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    #[\Override]
    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    #[\Override]
    public function void(Sale $sale): void
    {
        $entity = $this->entityManager->find(
            SaleEntity::class,
            $sale->movimiento_id
        );

        if (!$entity) {
            throw new ServiceException('No se encontró el movimiento de venta');
        }

        $entity->estado = 2;
        $entity->glosa = $sale->glosa;
        $entity->updated_by = $sale->updated_by;
        $entity->date_updated = new \DateTime();
        $this->entityManager->flush();
    }

    #[\Override]
    public function getSaleByCustomerId(string $id): PaginatorInterface
    {
        return $this->dataLoader->loadCollection(
            new LoadOptions(
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
            )
        );
    }
}
