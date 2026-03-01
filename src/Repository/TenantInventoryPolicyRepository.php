<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TenantInventoryPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantInventoryPolicy>
 */
final class TenantInventoryPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantInventoryPolicy::class);
    }
}
