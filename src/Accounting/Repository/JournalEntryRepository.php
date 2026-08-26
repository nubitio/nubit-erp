<?php

declare(strict_types=1);

namespace App\Accounting\Repository;

use App\Accounting\Entity\JournalEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalEntry>
 */
class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    public function findBySource(string $source, string $sourceId): ?JournalEntry
    {
        return $this->findOneBy(['source' => $source, 'sourceId' => $sourceId]);
    }

    public function nextEntryNo(int $year): string
    {
        $prefix = sprintf('A%s-', $year);
        $last = $this
            ->createQueryBuilder('e')
            ->select('e.entryNo')
            ->where('e.entryNo LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('e.entryNo', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $seq = 1;
        if (is_array($last) && isset($last['entryNo']) && is_string($last['entryNo'])) {
            $seq = (int) substr($last['entryNo'], strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
