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
     * Most recently opened session for a cash register, regardless of status
     * (open, closed, or reconciling) — used to answer "what's the current/
     * latest status of this register" rather than only "is it open now".
     */
    public function findLatestByCashRegister(int $cashRegisterId): ?CashSession
    {
        return $this
            ->createQueryBuilder('cs')
            ->addSelect('cr')
            ->join('cs.cashRegister', 'cr')
            ->where('cr.id = :id')
            ->setParameter('id', $cashRegisterId)
            ->orderBy('cs.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
