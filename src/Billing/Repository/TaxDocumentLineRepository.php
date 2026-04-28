<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\TaxDocumentLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocumentLine>
 */
class TaxDocumentLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxDocumentLine::class);
    }
}
