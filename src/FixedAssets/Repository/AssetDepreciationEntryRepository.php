<?php

declare(strict_types=1);

namespace App\FixedAssets\Repository;

use App\FixedAssets\Entity\AssetDepreciationEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetDepreciationEntry>
 */
class AssetDepreciationEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetDepreciationEntry::class);
    }
}
