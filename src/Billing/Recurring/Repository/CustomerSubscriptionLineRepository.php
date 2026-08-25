<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Repository;

use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CustomerSubscriptionLine> */
final class CustomerSubscriptionLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSubscriptionLine::class);
    }

    /** @return list<CustomerSubscriptionLine> */
    public function findForSubscription(CustomerSubscription $subscription): array
    {
        return $this->findBy(['subscription' => $subscription], ['sortOrder' => 'ASC', 'id' => 'ASC']);
    }
}
