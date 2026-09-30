<?php

declare(strict_types=1);

namespace App\Restaurant\Repository;

use App\Restaurant\Entity\RestaurantOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RestaurantOrder> */
class RestaurantOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantOrder::class);
    }

    public function findOpenForTable(int $tableId): ?RestaurantOrder
    {
        /** @var RestaurantOrder|null $result */
        $result = $this
            ->createQueryBuilder('o')
            ->andWhere('o.tableId = :tableId')
            ->andWhere('o.closedAt IS NULL')
            ->setParameter('tableId', $tableId)
            ->orderBy('o.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof RestaurantOrder ? $result : null;
    }
}
