<?php

namespace App\Department\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Department\Domain\Department;
use App\Department\Domain\DepartmentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DepartmentRepository implements DepartmentRepositoryInterface
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

    public function getOne(int $id): ?Department
    {
        $entity = $this->entityManager->find(DepartmentEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Department::class);
    }

    public function insert(Department $department): Department
    {
        $entity = DataMapper::map($department, DepartmentEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Department::class);
    }

    public function update(Department $department): Department
    {
        $entity = $this->entityManager->find(DepartmentEntity::class, $department->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($department, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Department::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(DepartmentEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
