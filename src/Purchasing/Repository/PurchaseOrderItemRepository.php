<?php

declare(strict_types=1);

namespace App\Purchasing\Repository;

use App\Purchasing\Entity\PurchaseOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseOrderItem>
 */
class PurchaseOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrderItem::class);
    }
}
