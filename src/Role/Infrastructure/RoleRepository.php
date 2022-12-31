<?php

namespace App\Role\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Role\Domain\Role;
use App\Role\Domain\RoleRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class RoleRepository implements RoleRepositoryInterface
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
        return $this->dataGridLoader->load('rol', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Role $role): void
    {
        $entity = new RoleEntity();
        $entity->mapFromDomainModel($role);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Role $role): void
    {
        $entity = $this->entityManager->find(RoleEntity::class, $role->rol_id);
        $entity->mapFromDomainModel($role);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(RoleEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
