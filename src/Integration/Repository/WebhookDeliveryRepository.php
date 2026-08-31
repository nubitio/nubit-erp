<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Webhook\WebhookDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookDelivery>
 */
class WebhookDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    /**
     * Deliveries owed an attempt: pending/failed with a due retry time,
     * oldest first so per-resource order holds.
     *
     * @return list<WebhookDelivery>
     */
    public function findDue(\DateTimeImmutable $now, int $limit = 100): array
    {
        /** @var list<WebhookDelivery> $rows */
        $rows = $this
            ->createQueryBuilder('d')
            ->where('d.status IN (:open)')
            ->andWhere('d.nextRetryAt IS NOT NULL')
            ->andWhere('d.nextRetryAt <= :now')
            ->setParameter('open', [WebhookDelivery::STATUS_PENDING, WebhookDelivery::STATUS_FAILED])
            ->setParameter('now', $now)
            ->orderBy('d.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<WebhookDelivery>
     */
    public function findByEventId(string $eventId): array
    {
        /** @var list<WebhookDelivery> $rows */
        $rows = $this->findBy(['eventId' => $eventId], ['createdAt' => 'ASC']);

        return $rows;
    }

    /**
     * Most recent delivery attempts against one endpoint, for the settings UI.
     *
     * @return list<WebhookDelivery>
     */
    public function findRecentForEndpoint(string $endpointId, int $limit = 20): array
    {
        /** @var list<WebhookDelivery> $rows */
        $rows = $this
            ->createQueryBuilder('d')
            ->where('d.endpoint = :endpoint')
            ->setParameter('endpoint', $endpointId)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
