<?php

namespace App\RoleMenu\Infrastructure;

use App\Core\Domain\Util\DataMapper;
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

    /**
     * @throws Exception
     */
    public function getAllByRole(int $roleId): array
    {
        return $this->connection->fetchAllAssociative('CALL sp_menu_by_rol(:roleId)', ['roleId' => $roleId]);
    }

    /**
     * @throws Exception
     */
    public function insertMultiple(int $roleId, array $roleMenuList): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('delete from menu_rol where rol_id = :roleId', ['roleId' => $roleId]);
            foreach ($roleMenuList as $roleMenu) {
                $entity = DataMapper::map($roleMenu, RoleMenuEntity::class);
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
