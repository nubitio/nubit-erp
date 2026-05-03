<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExchangeRate> */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    public function findByCurrency(string $currency): ?ExchangeRate
    {
        return $this->findOneBy(['currency' => strtoupper($currency)]);
    }
}
