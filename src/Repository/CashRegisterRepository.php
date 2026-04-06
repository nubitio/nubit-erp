<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CashRegister;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashRegister>
 */
class CashRegisterRepository extends ServiceEntityRepository
{
    /**
     * Sentinel name written by the enterprise-guardrails migration
     * (Version20260227180000) when a branch had no cash register at all.
     *
     * These rows are synthetic placeholders created to satisfy the branch-FK
     * constraints added in that migration. They are NOT real registers opened
     * by a user; audit and reporting queries should exclude them.
     *
     * @see isMigrationAuto()
     * @see findRealByBranchId()
     */
    public const string MIGRATION_AUTO_NAME = 'MIGRATION_AUTO';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashRegister::class);
    }

    /**
     * Returns only real (non-synthetic) registers for a branch.
     *
     * Excludes registers created by the enterprise-guardrails migration
     * (name = 'MIGRATION_AUTO').
     *
     * @return CashRegister[]
     */
    public function findRealByBranchId(int $branchId): array
    {
        return $this->createQueryBuilder('cr')
            ->where('cr.branch = :branchId')
            ->andWhere('cr.name != :sentinel')
            ->setParameter('branchId', $branchId)
            ->setParameter('sentinel', self::MIGRATION_AUTO_NAME)
            ->orderBy('cr.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
