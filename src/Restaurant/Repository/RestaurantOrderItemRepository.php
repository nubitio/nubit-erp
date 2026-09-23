<?php

declare(strict_types=1);

namespace App\Restaurant\Repository;

use App\Restaurant\Entity\RestaurantOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RestaurantOrderItem> */
class RestaurantOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantOrderItem::class);
    }
}
