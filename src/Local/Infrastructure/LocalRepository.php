<?php

namespace App\Local\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\Local\Domain\Local;
use App\Local\Domain\LocalRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class LocalRepository implements LocalRepositoryInterface
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
        return $this->dataGridLoader->load('vw_local', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Local $local): void
    {
        $entity = new LocalEntity();
        $entity->mapFromDomainModel($local);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Local $local): void
    {
        $entity = $this->entityManager->find(LocalEntity::class, $local->local_id);
        $entity->mapFromDomainModel($local);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(LocalEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getOneById(string $id): Local
    {
        $entity = $this->entityManager->find(LocalEntity::class, $id);

        return $entity->mapToDomainModel();
    }
}
