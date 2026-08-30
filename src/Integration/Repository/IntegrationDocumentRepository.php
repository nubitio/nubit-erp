<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Entity\IntegrationDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IntegrationDocument>
 */
class IntegrationDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegrationDocument::class);
    }

    public function findOneByExternalId(string $externalId): ?IntegrationDocument
    {
        return $this->findOneBy(['externalId' => strtolower($externalId)]);
    }

    public function findOneByReference(string $reference): ?IntegrationDocument
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
