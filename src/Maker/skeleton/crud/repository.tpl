<?php

namespace App\{{module}}\Infrastructure;

use App\{{module}}\Domain\{{module}}RepositoryInterface;
use App\{{module}}\Domain\{{module}};
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class {{module}}Repository implements {{module}}RepositoryInterface
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

    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('{{table}}', '{{sortColumn}}', filter: $filter);
    }

    public function insert({{module}} ${{camelModule}}): void
    {
        $entity = {{module}}Entity::mapFromDomainModel(${{camelModule}});
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update({{module}} ${{camelModule}}): void
    {
        $entity = $this->entityManager->find({{module}}Entity::class, ${{camelModule}}->{{table_id}});
        $entity->mapFromDomainModel(${{camelModule}});
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find({{module}}Entity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}