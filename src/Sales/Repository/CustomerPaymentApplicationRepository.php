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

        return $rows;
    }

    /**
     * What one request applied, summed per sale, for replaying a retried advance application.
     *
     * @return list<array{saleId: string, applied: string, currency: string, customerId: int}>
     */
    public function summaryForBatch(string $batchId): array
    {
        /** @var list<array{saleId: string, applied: string, currency: string, customerId: int|string}> $rows */
        $rows = $this
            ->createQueryBuilder('a')
            ->select('a.saleId AS saleId', 'SUM(a.appliedAmount) AS applied', 'a.currency AS currency', 'e.customerId AS customerId')
            ->join('a.accountEntry', 'e')
            ->where('a.batchId = :batch')
            ->setParameter('batch', $batchId)
            ->groupBy('a.saleId', 'a.currency', 'e.customerId')
            ->orderBy('MIN(a.id)', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $r): array => [
                'saleId' => (string) $r['saleId'],
                'applied' => (string) $r['applied'],
                'currency' => (string) $r['currency'],
                'customerId' => (int) $r['customerId'],
            ],
            $rows,
        );
    }
}
