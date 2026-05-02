<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\StockEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StockEntry> */
class StockEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockEntry::class);
    }
}
