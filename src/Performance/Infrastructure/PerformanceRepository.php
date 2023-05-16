<?php

namespace App\Performance\Infrastructure;

use App\ComboOption\Infrastructure\ComboOptionEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Performance\Domain\Performance;
use App\Performance\Domain\PerformanceRepositoryInterface;
use App\Product\Infrastructure\ProductEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class PerformanceRepository implements PerformanceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Performance
    {
        $entity = $this->entityManager->find(PerformanceEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Performance::class);
    }

    public function insert(Performance $performance): Performance
    {
        $entity = DataMapper::map($performance, PerformanceEntity::class);
        $line = $this->entityManager->getReference(ComboOptionEntity::class, $performance->lines?->id);
        $entity->lines = $line;
        $product = $this->entityManager->getReference(ProductEntity::class, $performance->products?->producto_id);
        $entity->products = $product;
        $type = $this->entityManager->getReference(ComboOptionEntity::class, $performance->types?->id);
        $entity->types = $type;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Performance::class);
    }

    public function update(Performance $performance): Performance
    {
        $entity = $this->entityManager->find(PerformanceEntity::class, $performance->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($performance, $entity);
        $line = $this->entityManager->getReference(ComboOptionEntity::class, $performance->lines?->id);
        $entity->lines = $line;
        $product = $this->entityManager->getReference(ProductEntity::class, $performance->products?->producto_id);
        $entity->products = $product;
        $type = $this->entityManager->getReference(ComboOptionEntity::class, $performance->types?->id);
        $entity->types = $type;
        $this->entityManager->flush();

        return DataMapper::map($entity, Performance::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PerformanceEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
