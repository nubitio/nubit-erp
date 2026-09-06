<?php

declare(strict_types=1);

namespace App\Pharmacy\Repository;

use App\Pharmacy\Entity\PharmacyBatchMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PharmacyBatchMovement> */
class PharmacyBatchMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PharmacyBatchMovement::class);
    }

    public function existsForSale(string $saleId): bool
    {
        return $this->count(['saleId' => $saleId]) > 0;
    }

    /**
     * @return list<PharmacyBatchMovement>
     */
    public function forSale(string $saleId): array
    {
        return $this->findBy(['saleId' => $saleId], ['id' => 'ASC']);
    }
}
