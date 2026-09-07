<?php

declare(strict_types=1);

namespace App\Pharmacy\Repository;

use App\Pharmacy\Entity\Prescription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Prescription> */
class PrescriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prescription::class);
    }

    /**
     * @return list<Prescription>
     */
    public function forSale(string $saleId): array
    {
        return $this->findBy(['saleId' => $saleId], ['id' => 'ASC']);
    }
}
