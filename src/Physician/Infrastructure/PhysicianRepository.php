<?php

namespace App\Physician\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Employee\Infrastructure\EmployeeEntity;
use App\Physician\Domain\Physician;
use App\Physician\Domain\PhysicianRepositoryInterface;
use App\Specialty\Infrastructure\SpecialtyEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class PhysicianRepository implements PhysicianRepositoryInterface
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

    public function getOne(int $id): ?Physician
    {
        $entity = $this->entityManager->find(PhysicianEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Physician::class);
    }

    public function insert(Physician $physician): Physician
    {
        $entity = DataMapper::map($physician, PhysicianEntity::class);
        $entity->employee = $this->entityManager->getReference(EmployeeEntity::class, $physician->employee?->id);
        $entity->specialty = $this->entityManager->getReference(SpecialtyEntity::class, $physician->specialty?->id);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Physician::class);
    }

    public function update(Physician $physician): Physician
    {
        $entity = $this->entityManager->find(PhysicianEntity::class, $physician->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($physician, $entity);
        $entity->employee = $this->entityManager->getReference(EmployeeEntity::class, $physician->employee?->id);
        $entity->specialty = $this->entityManager->getReference(SpecialtyEntity::class, $physician->specialty?->id);
        $this->entityManager->flush();

        return DataMapper::map($entity, Physician::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PhysicianEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
