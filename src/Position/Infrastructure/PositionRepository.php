<?php

namespace App\Position\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Department\Infrastructure\DepartmentEntity;
use App\Position\Domain\Position;
use App\Position\Domain\PositionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class PositionRepository implements PositionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?Position
    {
        $entity = $this->entityManager->find(PositionEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Position::class);
    }

    public function insert(Position $position): Position
    {
        $entity = DataMapper::map($position, PositionEntity::class);
        $entity->department = $this->entityManager->getReference(DepartmentEntity::class, $position->department?->id);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Position::class);
    }

    public function update(Position $position): Position
    {
        $entity = $this->entityManager->find(PositionEntity::class, $position->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($position, $entity);
        $entity->department = $this->entityManager->getReference(DepartmentEntity::class, $position->department?->id);
        $this->entityManager->flush();

        return DataMapper::map($entity, Position::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PositionEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
