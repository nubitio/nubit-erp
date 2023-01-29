<?php

namespace App\User\Infrastructure;

use App\User\Domain\UserRepositoryInterface;
use App\User\Domain\User;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $user) {
            $data[] = DataMapper::map($user, User::class);
        }

        return new EasyStore($paginator->getTotalItems(), $data);
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
        $entity = DataMapper::map($user, UserEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, User::class);
    }

    public function update(User $user): User
    {
        $entity = $this->entityManager->find(UserEntity::class, $user->usuario_id);
        $entity = DataMapper::map($user, $entity);
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