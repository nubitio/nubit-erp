<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\Entity\CollectionReminder;
use App\Sales\Reminder\ReminderChannel;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CollectionReminder>
 */
class CollectionReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionReminder::class);
    }

    /** Latest reminder for a sale, optionally restricted to one channel. */
    public function findLast(string $saleRecordId, ?ReminderChannel $channel = null): ?CollectionReminder
    {
        $criteria = ['saleRecordId' => $saleRecordId];
        if ($channel !== null) {
            $criteria['channel'] = $channel;
        }

        return $this->findOneBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @return list<CollectionReminder>
     */
    public function findHistory(?string $saleRecordId, ?int $customerId, int $limit): array
    {
        $criteria = array_filter(
            ['saleRecordId' => $saleRecordId, 'customerId' => $customerId],
            static fn(mixed $value): bool => $value !== null,
        );

        return array_values($this->findBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC'], $limit));
    }

    /** Deletes reminders created before the cutoff; returns how many were removed. */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('r')
            ->delete()
            ->where('r.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
