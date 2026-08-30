<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    /**
     * Look a key up by its public prefix. Returns revoked keys too — the
     * authenticator decides what to do with them so it can log the attempt.
     */
    public function findOneByPrefix(string $prefix): ?ApiKey
    {
        return $this->findOneBy(['prefix' => $prefix]);
    }
}
