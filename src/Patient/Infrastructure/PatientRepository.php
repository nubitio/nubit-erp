<?php

namespace App\Patient\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\FilterRule;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Customer\Infrastructure\CustomerEntity;
use App\Patient\Domain\Patient;
use App\Patient\Domain\PatientRepositoryInterface;
use App\PatientType\Infrastructure\PatientTypeEntity;
use App\PriceList\Infrastructure\PriceListEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class PatientRepository implements PatientRepositoryInterface
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

    public function getOne(int $id): ?Patient
    {
        $entity = $this->dataLoader->loadOne(new Options(
            entity: PatientEntity::class,
            mapping: Patient::class,
            joins: [
                new JoinRule('e.customer', 'c'),
                new JoinRule('e.type', 't'),
                new JoinRule('c.price_list', 'p', JoinType::LEFT),
            ],
            filters: [
                new FilterRule('id', FilterOperation::Equals, $id),
            ],
        ));

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Patient::class);
    }

    public function insert(Patient $patient): Patient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $patient->ruc);

        if (!$customer) {
            $customer = DataMapper::map($patient, CustomerEntity::class);
        } else {
            $customer = DataMapper::map($patient, $customer);
        }

        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $patient->price_list?->id);
        $entity = DataMapper::map($patient, PatientEntity::class);
        $entity->customer = $customer;
        $entity->type = $this->entityManager->getReference(PatientTypeEntity::class, $patient->type?->id);

        $this->entityManager->persist($customer);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Patient::class);
    }

    public function update(Patient $patient): Patient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $patient->ruc);
        $entity = $this->entityManager->find(PatientEntity::class, $patient->id);

        if (!$entity || !$customer) {
            throw new ServiceException('Entity not found');
        }

        /** @var CustomerEntity $customer */
        $customer = DataMapper::map($patient, $customer);
        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $patient->price_list?->id);
        /** @var PatientEntity $entity */
        $entity = DataMapper::map($patient, $entity);
        $entity->type = $this->entityManager->getReference(PatientTypeEntity::class, $patient->type?->id);
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
