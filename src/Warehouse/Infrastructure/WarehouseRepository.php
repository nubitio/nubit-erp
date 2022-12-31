<?php

namespace App\Warehouse\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_almacen', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Warehouse $warehouse): void
    {
        $entity = new WarehouseEntity();
        $entity->mapFromDomainModel($warehouse);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Warehouse $warehouse): void
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $warehouse->almacen_id);
        $entity->mapFromDomainModel($warehouse);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
