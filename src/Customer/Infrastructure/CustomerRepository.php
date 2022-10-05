<?php

namespace App\Customer\Infrastructure;

use App\Customer\Domain\CustomerRepositoryInterface;
use App\Customer\Domain\Customer;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CustomerRepository implements CustomerRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?string $filter = null): array
    {
        return $this->dataGridLoader->load('cliente', 'razon_social', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Customer $customer): void
    {
        $entity = new CustomerEntity();
        $entity->mapFromDomainModel($customer);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Customer $customer): void
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $customer->ruc);
        $entity->mapFromDomainModel($customer);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}