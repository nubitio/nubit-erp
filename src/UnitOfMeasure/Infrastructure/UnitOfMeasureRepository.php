<?php

namespace App\UnitOfMeasure\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\UnitOfMeasure\Domain\UnitOfMeasure;
use App\UnitOfMeasure\Domain\UnitOfMeasureRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class UnitOfMeasureRepository implements UnitOfMeasureRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?UnitOfMeasure
    {
        $entity = $this->entityManager->find(UnitOfMeasureEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, UnitOfMeasure::class);
    }

    public function insert(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        $entity = DataMapper::map($unitOfMeasure, UnitOfMeasureEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, UnitOfMeasure::class);
    }

    public function update(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        $entity = $this->entityManager->find(UnitOfMeasureEntity::class, $unitOfMeasure->unidad_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($unitOfMeasure, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, UnitOfMeasure::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(UnitOfMeasureEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
