<?php

namespace App\User\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class UserRepository implements UserRepositoryInterface
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
        return $this->dataGridLoader->load('vw_usuario', 'username', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(User $user): void
    {
        $entity = new UserEntity();
        $entity->mapFromDomainModel($user);
        $entity->password = password_hash($user->password, PASSWORD_DEFAULT);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(User $user): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $user->usuario_id);
        if (null !== $user->password && '' !== $user->password) {
            $user->password = password_hash($user->password, PASSWORD_DEFAULT);
        } else {
            $user->password = $entity->password;
        }
        $entity->mapFromDomainModel($user);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getOneByUsername(string $username): ?User
    {
        $entity = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['username' => $username]);

        return $entity?->mapToDomainModel() ?? null;
    }
}
