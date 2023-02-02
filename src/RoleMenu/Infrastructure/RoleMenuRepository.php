<?php

namespace App\RoleMenu\Infrastructure;

use App\RoleMenu\Domain\RoleMenu;
use App\RoleMenu\Domain\RoleMenuRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class RoleMenuRepository implements RoleMenuRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
    }

    public function getAllByRole(int $roleId): array
    {
        return $this->connection->fetchAllAssociative('CALL sp_menu_by_rol(:roleId)', ['roleId' => $roleId]);
    }

    /**
     * @param RoleMenu[] $roleMenuList
     *
     * @throws Exception
     */
    public function insertMultiple(int $roleId, array $roleMenuList): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('delete from menu_rol where rol_id = :roleId', ['roleId' => $roleId]);
            foreach ($roleMenuList as $roleMenu) {
                $entity = new RoleMenuEntity();
                $entity->mapFromDomainModel($roleMenu);
                $this->entityManager->persist($entity);
            }
            $this->entityManager->flush();
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
