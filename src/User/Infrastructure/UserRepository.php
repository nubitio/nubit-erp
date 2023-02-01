<?php

namespace App\User\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\Options;
use App\Role\Infrastructure\RoleEntity;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $user) {
            $data[] = DataMapper::map($user, User::class);
        }

        return new Paginator($data, $paginator);
    }

    public function getOne(string $id): ?User
    {
        /** @var ?UserEntity $entity */
        $entity = $this->entityManager->find(UserEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, User::class);
    }

    public function insert(User $user): User
    {
        /** @var UserEntity $entity */
        $entity = DataMapper::map($user, UserEntity::class);
        $role = $this->entityManager->getReference(RoleEntity::class, $user->rol->rol_id);
        $entity->rol = $role;
        $entity->password = password_hash($user->password, PASSWORD_DEFAULT);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, User::class);
    }

    public function update(User $user): User
    {
        $entity = $this->entityManager->find(UserEntity::class, $user->usuario_id);
        if ($user->password) {
            $user->password = password_hash($user->password, PASSWORD_DEFAULT);
        } else {
            $user->password = $entity->password;
        }
        $entity = DataMapper::map($user, $entity);
        $role = $this->entityManager->getReference(RoleEntity::class, $user->rol->rol_id);
        $entity->rol = $role;
        $this->entityManager->flush();

        return DataMapper::map($entity, User::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getOneByUsername(string $username): ?User
    {
        /** @var ?UserEntity $entity */
        $entity = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['username' => $username]);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, User::class);
    }
}