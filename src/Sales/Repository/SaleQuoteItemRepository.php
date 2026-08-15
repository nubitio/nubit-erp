<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\Entity\SaleQuoteItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleQuoteItem>
 */
class SaleQuoteItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleQuoteItem::class);
    }
}
