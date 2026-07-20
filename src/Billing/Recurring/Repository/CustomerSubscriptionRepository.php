<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Repository;

use App\Billing\Recurring\Entity\CustomerSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerSubscription>
 */
class CustomerSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSubscription::class);
    }
}
