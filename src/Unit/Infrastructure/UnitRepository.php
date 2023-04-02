<?php

namespace App\Unit\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Unit\Domain\Unit;
use App\Unit\Domain\UnitRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class UnitRepository implements UnitRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @inheritdoc
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('unidad', 'descripcion', filter: $filter);
    }

    public function insert(Unit $unit): void
    {
        $entity = new UnitEntity();
        $entity->mapFromDomainModel($unit);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(Unit $unit): void
    {
        $entity = $this->entityManager->find(UnitEntity::class, $unit->unidad_id);
        $entity->mapFromDomainModel($unit);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(UnitEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
