<?php

declare(strict_types=1);

namespace App\Payroll\Repository;

use App\Payroll\Entity\PayslipEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayslipEntry>
 */
class PayslipEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayslipEntry::class);
    }
}
