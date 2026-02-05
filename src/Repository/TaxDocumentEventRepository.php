<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TaxDocumentEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocumentEvent>
 */
class TaxDocumentEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxDocumentEvent::class);
    }
}
