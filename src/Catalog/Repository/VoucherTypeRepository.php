<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\VoucherType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoucherType>
 */
class VoucherTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoucherType::class);
    }
}
