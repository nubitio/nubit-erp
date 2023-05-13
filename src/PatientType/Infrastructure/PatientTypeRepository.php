<?php

namespace App\PatientType\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\PatientType\Domain\PatientType;
use App\PatientType\Domain\PatientTypeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class PatientTypeRepository implements PatientTypeRepositoryInterface
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

    public function getOne(int $id): ?PatientType
    {
        $entity = $this->entityManager->find(PatientTypeEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, PatientType::class);
    }

    public function insert(PatientType $patientType): PatientType
    {
        $entity = DataMapper::map($patientType, PatientTypeEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PatientType::class);
    }

    public function update(PatientType $patientType): PatientType
    {
        $entity = $this->entityManager->find(PatientTypeEntity::class, $patientType->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($patientType, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PatientType::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PatientTypeEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
