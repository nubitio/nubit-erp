<?php

declare(strict_types=1);

namespace App\Integration\Repository;

use App\Integration\Webhook\WebhookDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use SensitiveParameter;

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
        assert(is_array($rows), description: 'Doctrine query result must be an array.');

        return array_values(array_filter(
            $rows,
            static fn(mixed $delivery): bool => $delivery instanceof WebhookDelivery,
        ));
    }

    /** @return list<WebhookDelivery> */
    public function claimDue(\DateTimeImmutable $now, int $limit = 100, int $leaseSeconds = 60): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement(
            'UPDATE integration_webhook_delivery SET claim_token = NULL, claim_until = NULL WHERE claim_until IS NULL OR claim_until < :now',
            ['now' => $now],
            ['now' => Types::DATETIME_IMMUTABLE],
        );
        $ids = $connection->fetchFirstColumn(
            'SELECT id FROM integration_webhook_delivery WHERE status IN (:pending, :failed) AND next_retry_at IS NOT NULL AND next_retry_at <= :now AND claim_token IS NULL ORDER BY created_at ASC LIMIT '
                . max(1, $limit),
            ['pending' => WebhookDelivery::STATUS_PENDING, 'failed' => WebhookDelivery::STATUS_FAILED, 'now' => $now],
            ['pending' => ParameterType::STRING, 'failed' => ParameterType::STRING, 'now' => Types::DATETIME_IMMUTABLE],
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
                    'UPDATE integration_webhook_delivery SET claim_token = :token, claim_until = :until WHERE id = :id AND claim_token IS NULL',
                    ['token' => $token, 'until' => $until, 'id' => $id],
                    ['until' => Types::DATETIME_IMMUTABLE],
                )
            ) {
                continue;
            }
            $claimed[] = $id;
        }
        return $this->findBy(['id' => $claimed], ['createdAt' => 'ASC']);
    }

    public function ownsClaim(string $id, #[SensitiveParameter] string $token, \DateTimeImmutable $now): bool
    {
        return 1
        === (int) $this
            ->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT 1 FROM integration_webhook_delivery WHERE id = :id AND claim_token = :token AND claim_until >= :now AND status IN (:pending, :failed)',
                [
                    'id' => $id,
                    'token' => $token,
                    'now' => $now,
                    'pending' => WebhookDelivery::STATUS_PENDING,
                    'failed' => WebhookDelivery::STATUS_FAILED,
                ],
                [
                    'pending' => ParameterType::STRING,
                    'failed' => ParameterType::STRING,
                    'now' => Types::DATETIME_IMMUTABLE,
                ],
            );
    }

    /**
     * @return list<WebhookDelivery>
     */
    public function findByEventId(string $eventId): array
    {
        return $this->findBy(['eventId' => $eventId], ['createdAt' => 'ASC']);
    }

    /**
     * Most recent delivery attempts against one endpoint, for the settings UI.
     *
     * @return list<WebhookDelivery>
     */
    public function findRecentForEndpoint(string $endpointId, int $limit = 20): array
    {
        $rows = $this
            ->createQueryBuilder('d')
            ->where('d.endpoint = :endpoint')
            ->setParameter('endpoint', $endpointId)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        assert(is_array($rows), description: 'Doctrine query result must be an array.');

        return array_values(array_filter(
            $rows,
            static fn(mixed $delivery): bool => $delivery instanceof WebhookDelivery,
        ));
    }
}
