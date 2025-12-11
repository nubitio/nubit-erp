<?php

namespace App\Repository;

use DateTimeInterface;
use App\Entity\CreditNote;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditNote>
 */
class CreditNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditNote::class);
    }

    public function save(CreditNote $creditNote, bool $flush = false): void
    {
        $this->getEntityManager()->persist($creditNote);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CreditNote $creditNote, bool $flush = false): void
    {
        $this->getEntityManager()->remove($creditNote);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find credit notes by sale
     *
     * @return CreditNote[]
     */
    public function findBySale(Sale $sale): array
    {
        return $this->createQueryBuilder('cn')
            ->where('cn.sale = :sale')
            ->setParameter('sale', $sale)
            ->orderBy('cn.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find credit notes by date range
     *
     * @return CreditNote[]
     */
    public function findByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('cn')
            ->where('cn.fecha BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('cn.fecha', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending credit notes (not sent to SUNAT)
     *
     * @return CreditNote[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('cn')
            ->where('cn.envioSunat IS NULL OR cn.envioSunat = :pending')
            ->setParameter('pending', 'pendiente')
            ->orderBy('cn.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next correlativo for a given serie
     */
    public function getNextCorrelativo(string $serie): int
    {
        $result = $this->createQueryBuilder('cn')
            ->select('MAX(cn.correlativo)')
            ->where('cn.serie = :serie')
            ->setParameter('serie', $serie)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? ((int)$result + 1) : 1;
    }

    /**
     * Get total amount of credit notes in a date range
     */
    public function getTotalByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('cn')
            ->select('SUM(cn.total)')
            ->where('cn.fecha BETWEEN :startDate AND :endDate')
            ->andWhere('cn.envioSunat = :accepted')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('accepted', 'aceptado')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0.0;
    }

    /**
     * Count credit notes by status
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('cn')
            ->select('COUNT(cn.id)')
            ->where('cn.envioSunat = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
