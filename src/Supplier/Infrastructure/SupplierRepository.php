<?php

namespace App\Supplier\Infrastructure;

use App\Supplier\Domain\SupplierRepositoryInterface;
use App\Supplier\Domain\Supplier;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class SupplierRepository implements SupplierRepositoryInterface
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
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('proveedor', 'razon_social', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Supplier $supplier): void
    {
        $entity = new SupplierEntity();
        $entity->mapFromDomainModel($supplier);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Supplier $supplier): void
    {
        $entity = $this->entityManager->find(SupplierEntity::class, $supplier->proveedor_id);
        $entity->mapFromDomainModel($supplier);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SupplierEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}