<?php

namespace App\Orden\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Mesa\Infrastructure\MesaEntity;
use App\Orden\Domain\Orden;
use App\Orden\Domain\OrdenRepositoryInterface;
use App\OrdenDetail\Infrastructure\OrdenDetailEntity;
use App\Product\Infrastructure\ProductEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class OrdenRepository implements OrdenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Orden
    {
        $entity = $this->entityManager->find(OrdenEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Orden::class);
    }

    public function insert(Orden $orden): Orden
    {
        $entity = DataMapper::map($orden, OrdenEntity::class);
        $table = $this->entityManager->getReference(MesaEntity::class, $orden->mesa?->mesa_id);
        $entity->mesa = $table;
        /** @var OrdenDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->orden = $entity;
            $detail->producto = $this->entityManager->getReference(ProductEntity::class, $detail->producto?->producto_id);
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Orden::class);
    }

    public function update(Orden $orden): Orden
    {
        $entity = $this->entityManager->find(OrdenEntity::class, $orden->orden_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($orden, $entity);
        $table = $this->entityManager->getReference(MesaEntity::class, $orden->mesa?->mesa_id);
        $entity->mesa = $table;
        /** @var OrdenDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->orden = $entity;
            $detail->producto = $this->entityManager->getReference(ProductEntity::class, $detail->producto?->producto_id);
        }
        $this->entityManager->flush();

        return DataMapper::map($entity, Orden::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(OrdenEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
