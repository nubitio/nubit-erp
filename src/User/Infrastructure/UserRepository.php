<?php

namespace App\User\Infrastructure;

use App\User\Domain\UserRepositoryInterface;
use App\User\Domain\User;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class UserRepository implements UserRepositoryInterface
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
        return $this->dataGridLoader->load('usuario', 'password', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(User $user): void
    {
        $entity = new UserEntity();
        $entity->mapFromDomainModel($user);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(User $user): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $user->getId());
        $entity->mapFromDomainModel($user);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}