<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\StockEntryItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StockEntryItem> */
class StockEntryItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockEntryItem::class);
    }
}
