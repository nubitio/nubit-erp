<?php

declare(strict_types=1);

namespace App\Restaurant\Repository;

use App\Restaurant\Entity\RestaurantArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RestaurantArea> */
class RestaurantAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantArea::class);
    }
}
