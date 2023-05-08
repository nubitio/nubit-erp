<?php

namespace App\MilitaryPatient\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Customer\Infrastructure\CustomerEntity;
use App\MilitaryPatient\Domain\MilitaryPatient;
use App\MilitaryPatient\Domain\MilitaryPatientRepositoryInterface;
use App\Patient\Infrastructure\PatientEntity;
use App\PatientType\Infrastructure\PatientTypeEntity;
use App\PriceList\Infrastructure\PriceListEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

readonly class MilitaryPatientRepository implements MilitaryPatientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?MilitaryPatient
    {
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function insert(MilitaryPatient $militaryPatient): MilitaryPatient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $militaryPatient->ruc);
        $patient = $this->entityManager->find(PatientEntity::class, $militaryPatient->patient?->id ?? 0);

        if (!$customer) {
            $customer = DataMapper::map($militaryPatient, CustomerEntity::class);
        } else {
            $customer = DataMapper::map($militaryPatient, $customer);
        }

        if (!$patient) {
            $patient = DataMapper::map($militaryPatient, PatientEntity::class);
        } else {
            $patient = DataMapper::map($militaryPatient, $patient);
        }

        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $militaryPatient->price_list?->id);
        $patient->customer = $customer;
        $patient->type = $this->entityManager->getReference(PatientTypeEntity::class, $militaryPatient->patient?->type?->id);
        $entity = DataMapper::map($militaryPatient, MilitaryPatientEntity::class);
        $entity->patient = $patient;
        $this->entityManager->persist($customer);
        $this->entityManager->persist($patient);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function update(MilitaryPatient $militaryPatient): MilitaryPatient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $militaryPatient->ruc);
        $patient = $this->entityManager->find(PatientEntity::class, $militaryPatient->patient?->id ?? 0);
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $militaryPatient->id);

        if (!$entity || !$customer || !$patient) {
            throw new ServiceException('Entity not found');
        }

        /** @var CustomerEntity $customer */
        $customer = DataMapper::map($militaryPatient, $customer);
        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $militaryPatient->price_list?->id);
        /** @var PatientEntity $patient */
        $patient = DataMapper::map($militaryPatient, $patient, ['id']);
        $patient->customer = $customer;
        $patient->type = $this->entityManager->getReference(PatientTypeEntity::class, $militaryPatient->type?->id);
        $entity = DataMapper::map($militaryPatient, $entity);
        $entity->patient = $patient;
        $this->entityManager->flush();

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getOneByCustomerId(string $id): ?MilitaryPatient
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MilitaryPatientEntity::class, 'm')
            ->join('m.patient', 'p')
            ->join('p.customer', 'c')
            ->where('c.ruc = :id')
            ->setParameter('id', $id)
            ->getQuery();

        return DataMapper::map($query->getOneOrNullResult(), MilitaryPatient::class);
    }
}