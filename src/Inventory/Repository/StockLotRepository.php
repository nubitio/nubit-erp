<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\StockLot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLot>
 */
class StockLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockLot::class);
    }

    /**
     * Find lots for FIFO deduction: oldest received_at first.
     *
     * @return StockLot[]
     */
    public function findForFifo(string $productId, string $warehouseId): array
    {
        return $this
            ->createQueryBuilder('sl')
            ->where('IDENTITY(sl.product) = :productId')
            ->andWhere('IDENTITY(sl.warehouse) = :warehouseId')
            ->andWhere('sl.qtyOnHand > 0')
            ->setParameter('productId', $productId)
            ->setParameter('warehouseId', $warehouseId)
            ->orderBy('sl.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find lots for FEFO deduction: nearest expiration_date first,
     * with NULL expiration (non-perishable) deducted last.
     *
     * @return StockLot[]
     */
    public function findForFefo(string $productId, string $warehouseId): array
    {
        return $this
            ->createQueryBuilder('sl')
            ->where('IDENTITY(sl.product) = :productId')
            ->andWhere('IDENTITY(sl.warehouse) = :warehouseId')
            ->andWhere('sl.qtyOnHand > 0')
            ->setParameter('productId', $productId)
            ->setParameter('warehouseId', $warehouseId)
            ->orderBy('CASE WHEN sl.expirationDate IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('sl.expirationDate', 'ASC')
            ->addOrderBy('sl.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLotNumber(string $productId, string $warehouseId, string $lotNumber): ?StockLot
    {
        return $this
            ->createQueryBuilder('sl')
            ->where('IDENTITY(sl.product) = :productId')
            ->andWhere('IDENTITY(sl.warehouse) = :warehouseId')
            ->andWhere('sl.lotNumber = :lotNumber')
            ->setParameter('productId', $productId)
            ->setParameter('warehouseId', $warehouseId)
            ->setParameter('lotNumber', $lotNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
