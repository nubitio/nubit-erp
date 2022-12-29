<?php

namespace App\Operation\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\Operation\Domain\Operation;
use App\Operation\Domain\OperationRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class OperationRepository implements OperationRepositoryInterface
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
        return $this->dataGridLoader->load('operacion', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Operation $operation): void
    {
        $entity = new OperationEntity();
        $entity->mapFromDomainModel($operation);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Operation $operation): void
    {
        $entity = $this->entityManager->find(OperationEntity::class, $operation->operacion_id);
        $entity->mapFromDomainModel($operation);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(OperationEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
