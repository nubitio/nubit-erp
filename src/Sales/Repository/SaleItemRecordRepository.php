<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\Entity\SaleItemRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleItemRecord>
 */
class SaleItemRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleItemRecord::class);
    }
}
