<?php

namespace App\Consultation\Infrastructure;

use App\Consultation\Domain\Consultation;
use App\Consultation\Domain\ConsultationRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Patient\Infrastructure\PatientEntity;
use App\Physician\Infrastructure\PhysicianEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class ConsultationRepository implements ConsultationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private ConsultationUtils $consultationUtils,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?Consultation
    {
        $entity = $this->entityManager->find(ConsultationEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Consultation::class);
    }

    public function insert(Consultation $consultation): Consultation
    {
        $consultation = $this->consultationUtils->fillQueueValue($consultation);
        $entity = DataMapper::map($consultation, ConsultationEntity::class);
        $entity->patient = $this->entityManager->find(PatientEntity::class, $consultation->patient?->id);
        $entity->physician = $this->entityManager->find(PhysicianEntity::class, $consultation->physician?->id);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Consultation::class);
    }

    public function update(Consultation $consultation): Consultation
    {
        $entity = $this->entityManager->find(ConsultationEntity::class, $consultation->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($consultation, $entity);
        $entity->patient = $this->entityManager->find(PatientEntity::class, $consultation->patient?->id);
        $entity->physician = $this->entityManager->find(PhysicianEntity::class, $consultation->physician?->id);
        $this->entityManager->flush();

        return DataMapper::map($entity, Consultation::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(ConsultationEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
