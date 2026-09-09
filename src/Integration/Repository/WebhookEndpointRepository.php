<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Webhook\WebhookEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookEndpoint>
 */
class WebhookEndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEndpoint::class);
    }

    /**
     * @return list<WebhookEndpoint>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['status' => WebhookEndpoint::STATUS_ENABLED]);
    }
}
