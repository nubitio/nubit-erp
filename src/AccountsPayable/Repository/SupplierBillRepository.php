<?php

declare(strict_types=1);

namespace App\AccountsPayable\Repository;

use App\AccountsPayable\Entity\SupplierBill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplierBill>
 */
class SupplierBillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplierBill::class);
    }
}
