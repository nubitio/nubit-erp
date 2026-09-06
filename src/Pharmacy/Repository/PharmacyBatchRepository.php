<?php

declare(strict_types=1);

namespace App\Pharmacy\Repository;

use App\Pharmacy\Entity\PharmacyBatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PharmacyBatch> */
class PharmacyBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PharmacyBatch::class);
    }

    /**
     * Batches for a product, earliest expiry first (FEFO order).
     *
     * @return list<PharmacyBatch>
     */
    public function fefoForProduct(int $productId): array
    {
        return $this->findBy(['productId' => $productId], ['expirationDate' => 'ASC']);
    }

    /**
     * Batches expiring on or before the cutoff, earliest first.
     *
     * @return list<PharmacyBatch>
     */
    public function expiringBy(\DateTimeImmutable $cutoff): array
    {
        /** @var list<PharmacyBatch> $rows */
        $rows = $this
            ->createQueryBuilder('b')
            ->andWhere('b.expirationDate <= :cutoff')
            ->setParameter('cutoff', $cutoff->setTime(0, 0))
            ->orderBy('b.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
