<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Event\IntegrationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use SensitiveParameter;

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

    /** @return list<IntegrationEvent> */
    public function claimPending(int $limit, \DateTimeImmutable $now, int $leaseSeconds = 300): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement(
            'UPDATE integration_event SET status = :pending, claim_token = NULL, claim_until = NULL WHERE status = :processing AND (claim_until IS NULL OR claim_until < :now)',
            [
                'pending' => IntegrationEvent::STATUS_PENDING,
                'processing' => IntegrationEvent::STATUS_PROCESSING,
                'now' => $now,
            ],
            ['now' => Types::DATETIME_IMMUTABLE],
        );
        $ids = $connection->fetchFirstColumn(
            'SELECT id FROM integration_event WHERE status = :pending ORDER BY occurred_at ASC LIMIT ' . max(1, $limit),
            ['pending' => IntegrationEvent::STATUS_PENDING],
        );
        if ([] === $ids) {
            return [];
        }
        $token = bin2hex(random_bytes(32));
        $until = $now->modify(sprintf('+%d seconds', $leaseSeconds));
        $claimed = [];
        foreach ($ids as $id) {
            if (
                1 !== $connection->executeStatement(
                    'UPDATE integration_event SET status = :processing, claim_token = :token, claim_until = :until WHERE id = :id AND status = :pending',
                    [
                        'processing' => IntegrationEvent::STATUS_PROCESSING,
                        'token' => $token,
                        'until' => $until,
                        'id' => $id,
                        'pending' => IntegrationEvent::STATUS_PENDING,
                    ],
                    ['until' => Types::DATETIME_IMMUTABLE],
                )
            ) {
                continue;
            }
            $claimed[] = $id;
        }
        return $this->findBy(['id' => $claimed], ['occurredAt' => 'ASC']);
    }

    public function ownsClaim(string $id, #[SensitiveParameter] string $token, \DateTimeImmutable $now): bool
    {
        return 1
        === (int) $this
            ->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT 1 FROM integration_event WHERE id = :id AND status = :status AND claim_token = :token AND claim_until >= :now',
                [
                    'id' => $id,
                    'status' => IntegrationEvent::STATUS_PROCESSING,
                    'token' => $token,
                    'now' => $now,
                ],
                ['now' => Types::DATETIME_IMMUTABLE],
            );
    }
}
