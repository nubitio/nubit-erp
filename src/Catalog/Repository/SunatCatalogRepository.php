<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\SunatCatalog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SunatCatalog>
 */
class SunatCatalogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SunatCatalog::class);
    }
}
