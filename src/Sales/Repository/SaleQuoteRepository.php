<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\Entity\SaleQuote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleQuote>
 */
class SaleQuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleQuote::class);
    }
}
