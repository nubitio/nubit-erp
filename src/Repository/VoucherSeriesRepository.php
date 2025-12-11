<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VoucherSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoucherSeries>
 */
class VoucherSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoucherSeries::class);
    }
}
