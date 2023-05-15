<?php

namespace App\Role\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Role\Domain\Role;
use App\Role\Domain\RoleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Role
    {
        /** @var ?RoleEntity $entity */
        $entity = $this->entityManager->find(RoleEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Role::class);
    }

    public function insert(Role $role): Role
    {
        $entity = DataMapper::map($role, RoleEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Role::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(Role $role): Role
    {
        $entity = $this->entityManager->find(RoleEntity::class, $role->rol_id);

        if (!$entity) {
            throw new ServiceException('El rol no existe');
        }

        $entity = DataMapper::map($role, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Role::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(RoleEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
