<?php

namespace App\Config\Infrastructure;

use App\Config\Domain\ConfigRepositoryInterface;
use App\Config\Domain\Config;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class ConfigRepository implements ConfigRepositoryInterface
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
        return $this->dataGridLoader->load('options', 'name', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Config $config): void
    {
        $entity = new ConfigEntity();
        $entity->mapFromDomainModel($config);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Config $config): void
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $config->id);
        $entity->mapFromDomainModel($config);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}