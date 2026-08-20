<?php

declare(strict_types=1);

namespace App\Payroll\Repository;

use App\Payroll\Entity\PayrollRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayrollRun>
 */
class PayrollRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayrollRun::class);
    }
}
