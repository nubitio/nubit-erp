<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Event\IntegrationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IntegrationEvent>
 */
class IntegrationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegrationEvent::class);
    }

    /**
     * Oldest pending events first, so per-resource order is preserved.
     *
     * @return list<IntegrationEvent>
     */
    public function findPending(int $limit = 100): array
    {
        $rows = $this
            ->createQueryBuilder('e')
            ->where('e.status = :pending')
            ->setParameter('pending', IntegrationEvent::STATUS_PENDING)
            ->orderBy('e.occurredAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        assert(is_array($rows), description: 'Doctrine query result must be an array.');

        return array_values(array_filter($rows, static fn(mixed $event): bool => $event instanceof IntegrationEvent));
    }
}
