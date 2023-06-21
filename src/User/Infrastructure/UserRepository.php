<?php

namespace App\User\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Role\Infrastructure\RoleEntity;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?User
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
        $role = $this->entityManager->getReference(RoleEntity::class, $user->rol?->rol_id);
        $entity->rol = $role;
        $entity->password = password_hash((string) $user->password, PASSWORD_DEFAULT);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, User::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(User $user): User
    {
        $entity = $this->entityManager->find(UserEntity::class, $user->usuario_id);

        if (!$entity) {
            throw new ServiceException('El usuario no existe');
        }

        if ($user->password) {
            $user->password = password_hash($user->password, PASSWORD_DEFAULT);
        } else {
            $user->password = $entity->password;
        }

        $entity = DataMapper::map($user, $entity);
        $role = $this->entityManager->getReference(RoleEntity::class, $user->rol?->rol_id);
        $entity->rol = $role;
        $this->entityManager->flush();

        return DataMapper::map($entity, User::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $id);

        if (!$entity) {
            return;
        }

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

    /**
     * @throws ServiceException
     */
    public function changePassword(int $userId, string $newPassword): void
    {
        $entity = $this->entityManager->find(UserEntity::class, $userId);

        if (!$entity) {
            throw new ServiceException('El usuario no existe');
        }

        $entity->password = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->entityManager->flush();
    }
}
