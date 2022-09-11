<?php

namespace App\Unit\Infrastructure;

use App\Unit\Domain\UnitRepositoryInterface;
use App\Unit\Domain\Unit;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class UnitRepository implements UnitRepositoryInterface
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
        return $this->dataGridLoader->load('unidad', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Unit $unit): void
    {
        $entity = new UnitEntity();
        $entity->mapFromDomainModel($unit);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Unit $unit): void
    {
        $entity = $this->entityManager->find(UnitEntity::class, $unit->unidad_id);
        $entity->mapFromDomainModel($unit);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(UnitEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}