<?php

namespace App\Customer\Infrastructure;

use App\Customer\Domain\CustomerRepositoryInterface;
use App\Customer\Domain\Customer;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\ORM\EntityManagerInterface;

readonly class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $customer) {
            $data[] = DataMapper::map($customer, Customer::class);
        }

        return new EasyStore($paginator->getTotalItems(), $data);
    }

    public function getOne(string $id): ?Customer
    {
        /** @var ?CustomerEntity $entity */
        $entity = $this->entityManager->find(CustomerEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Customer::class);
    }

    public function insert(Customer $customer): Customer
    {
        $entity = DataMapper::map($customer, CustomerEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Customer::class);
    }

    public function update(Customer $customer): Customer
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $customer->ruc);
        $entity = DataMapper::map($customer, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Customer::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}