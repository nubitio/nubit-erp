<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use App\Settings\Entity\Ubigeo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ubigeo>
 */
class UbigeoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ubigeo::class);
    }
}
