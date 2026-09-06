<?php

declare(strict_types=1);

namespace App\Pharmacy\Repository;

use App\Pharmacy\Entity\PharmacyProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PharmacyProduct> */
class PharmacyProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PharmacyProduct::class);
    }

    public function findForProduct(int $productId): ?PharmacyProduct
    {
        return $this->findOneBy(['productId' => $productId]);
    }
}
