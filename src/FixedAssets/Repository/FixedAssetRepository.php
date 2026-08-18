<?php

declare(strict_types=1);

namespace App\FixedAssets\Repository;

use App\FixedAssets\Entity\FixedAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FixedAsset>
 */
class FixedAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FixedAsset::class);
    }
}
