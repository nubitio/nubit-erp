<?php

declare(strict_types=1);

namespace App\CashRegister\Repository;

use App\CashRegister\Entity\CashMovementCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashMovementCategory>
 */
class CashMovementCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashMovementCategory::class);
    }
}
