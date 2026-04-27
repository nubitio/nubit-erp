<?php

declare(strict_types=1);

namespace App\CashRegister\Repository;

use App\CashRegister\Entity\CashSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashSession>
 */
class CashSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashSession::class);
    }

    /**
     * Find a CashSession by its legacy cash_shift foreign-key value.
     */
    public function findByLegacyCashShiftId(int $legacyCashShiftId): ?CashSession
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.legacyCashShiftId = :id')
            ->setParameter('id', $legacyCashShiftId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
