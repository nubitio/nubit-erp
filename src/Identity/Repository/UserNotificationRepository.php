<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\UserNotification;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception as DbalException;
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
     * Uses raw DBAL (not ORM) so the query can be tested with a simple
     * Connection mock and is consistent with UserNotificationService's pattern.
     *
     * Used by GenerateStockNotificationsCommand to deduplicate stock_low
     * notifications within a 24-hour sliding window.
     *
     * @throws DbalException
     */
    public function existsUnreadForUserAndType(int $userId, string $type, DateTimeImmutable $since): bool
    {
        $count = (int) $this
            ->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM user_notification
                  WHERE user_id = ? AND type = ? AND read_at IS NULL AND created_at >= ?', [
                $userId,
                $type,
                $since->format('Y-m-d H:i:s'),
            ]);

        return $count > 0;
    }
}
