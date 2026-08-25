<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Repository;

use App\Billing\Recurring\Entity\CustomerSubscriptionRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CustomerSubscriptionRun> */
final class CustomerSubscriptionRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSubscriptionRun::class);
    }
}
