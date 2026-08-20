<?php

declare(strict_types=1);

namespace App\Payroll\Repository;

use App\Payroll\Entity\AfpRate;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AfpRate>
 */
class AfpRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AfpRate::class);
    }

    /**
     * Latest rate for the given AFP effective on or before $asOf.
     */
    public function findEffectiveRate(string $afpProvider, DateTimeImmutable $asOf): ?AfpRate
    {
        return $this
            ->createQueryBuilder('r')
            ->andWhere('r.afpProvider = :provider')
            ->andWhere('r.effectiveFrom <= :asOf')
            ->setParameter('provider', $afpProvider)
            ->setParameter('asOf', $asOf)
            ->orderBy('r.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
