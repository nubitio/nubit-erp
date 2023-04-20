<?php

namespace App\Patient\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Patient\Domain\PatientRepositoryInterface;
use App\Patient\Domain\Patient;
use Doctrine\ORM\EntityManagerInterface;

readonly class PatientRepository implements PatientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Patient
    {
        $entity = $this->entityManager->find(PatientEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Patient::class);
    }

    public function insert(Patient $patient): Patient
    {
        $entity = DataMapper::map($patient, PatientEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Patient::class);
    }

    public function update(Patient $patient): Patient
    {
        $entity = $this->entityManager->find(PatientEntity::class, $patient->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($patient, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Patient::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PatientEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}