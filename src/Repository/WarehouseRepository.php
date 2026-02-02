<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warehouse>
 */
class WarehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warehouse::class);
    }

    /**
     * @return string[]
     */
    public function findIdsByBranchId(string $branchId): array
    {
        $result = $this->createQueryBuilder('w')
            ->select('w.id')
            ->where('w.branch = :branchId')
            ->setParameter('branchId', $branchId)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'id');
    }
}
