<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\UserNotification;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserNotification>
 *
 * @method UserNotification|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserNotification|null findOneBy(array<string, mixed> $criteria, array<string, mixed>|null $orderBy = null)
 * @method UserNotification[]    findAll()
 * @method UserNotification[]    findBy(array<string, mixed> $criteria, array<string, mixed>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserNotification::class);
    }

    /**
     * Returns true when an unread notification of the given type already exists
     * for the given user, created at or after $since.
     *
     * Used by GenerateStockNotificationsCommand to deduplicate stock_low
     * notifications within a 24-hour sliding window.
     */
    public function existsUnreadForUserAndType(int $userId, string $type, DateTimeImmutable $since): bool
    {
        return (
            (int) $this
                ->getEntityManager()
                ->createQuery(<<<'DQL'
                    SELECT COUNT(n.id)
                    FROM App\Identity\Entity\UserNotification n
                    WHERE n.userId = :userId
                      AND n.type = :type
                      AND n.readAt IS NULL
                      AND n.createdAt >= :since
                    DQL)
                ->setParameter('userId', $userId)
                ->setParameter('type', $type)
                ->setParameter('since', $since)
                ->getSingleScalarResult() > 0
        );
    }

    public function markAllUnreadAsRead(int $userId, DateTimeImmutable $readAt): void
    {
        $this
            ->getEntityManager()
            ->createQuery(<<<'DQL'
                UPDATE App\Identity\Entity\UserNotification n
                SET n.readAt = :readAt
                WHERE n.userId = :userId
                  AND n.readAt IS NULL
                DQL)
            ->setParameter('readAt', $readAt)
            ->setParameter('userId', $userId)
            ->execute();
    }
}
