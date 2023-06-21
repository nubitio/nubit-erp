<?php

namespace App\Employee\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Employee\Domain\Employee;
use App\Employee\Domain\EmployeeRepositoryInterface;
use App\Position\Infrastructure\PositionEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class EmployeeRepository implements EmployeeRepositoryInterface
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

    public function getOne(int $id): ?Employee
    {
        $entity = $this->entityManager->find(EmployeeEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Employee::class);
    }

    public function insert(Employee $employee): Employee
    {
        $entity = DataMapper::map($employee, EmployeeEntity::class);
        $entity->position = $this->entityManager->getReference(PositionEntity::class, $employee->position?->id);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Employee::class);
    }

    public function update(Employee $employee): Employee
    {
        $entity = $this->entityManager->find(EmployeeEntity::class, $employee->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($employee, $entity);
        $entity->position = $this->entityManager->getReference(PositionEntity::class, $employee->position?->id);
        $this->entityManager->flush();

        return DataMapper::map($entity, Employee::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(EmployeeEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
