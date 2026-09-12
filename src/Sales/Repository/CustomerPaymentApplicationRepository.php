<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\Entity\CustomerPaymentApplication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPaymentApplication>
 */
class CustomerPaymentApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPaymentApplication::class);
    }

    /**
     * Total already applied to a sale through the customer-account layer.
     *
     * @return list<CustomerPaymentApplication>
     */
    public function forSale(string $saleId): array
    {
        /** @var list<CustomerPaymentApplication> $rows */
        $rows = $this
            ->createQueryBuilder('a')
            ->where('a.saleId = :sale')
            ->setParameter('sale', $saleId)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        return [...$rows];
    }
}
