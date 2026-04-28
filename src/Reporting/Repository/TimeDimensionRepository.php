<?php

declare(strict_types=1);

namespace App\Reporting\Repository;

use App\Reporting\Entity\TimeDimension;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeDimension>
 */
class TimeDimensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeDimension::class);
    }
}
