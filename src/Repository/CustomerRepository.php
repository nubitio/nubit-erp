<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Return the tenant's generic / anonymous customer (ruc = '00000000').
     *
     * This customer is seeded during tenant provisioning by InitialDataSeeder
     * and InitialSetupService. Using the stable RUC instead of a hard-coded
     * primary-key integer (ID 1) makes the lookup tenant-safe.
     */
    public function findDefaultAnonymous(): ?Customer
    {
        return $this->findOneBy(['ruc' => '00000000']);
    }
}
