<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Entity\ApiClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiClient>
 */
class ApiClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiClient::class);
    }
}
