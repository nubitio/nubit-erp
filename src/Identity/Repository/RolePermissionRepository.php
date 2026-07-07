<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RolePermission>
 */
class RolePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    /**
     * @return list<string>
     */
    public function findPermissionKeysByRoleId(int $roleId): array
    {
        $rows = $this
            ->createQueryBuilder('rp')
            ->select('rp.permission')
            ->andWhere('rp.role = :roleId')
            ->setParameter('roleId', $roleId)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(strval(...), $rows));
    }
}
