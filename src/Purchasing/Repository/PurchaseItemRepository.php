<?php

declare(strict_types=1);

namespace App\Purchasing\Repository;

use App\Purchasing\Entity\PurchaseItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseItem>
 */
class PurchaseItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseItem::class);
    }
}
