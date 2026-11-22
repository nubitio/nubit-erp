<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Billing\Entity\CreditNoteDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditNoteDraft>
 */
class CreditNoteDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditNoteDraft::class);
    }

    /** Drafts are personal: only their author sees them, and only in the branch they were started in. */
    public function findOwned(string $id, string $branchId, int $userId): ?CreditNoteDraft
    {
        return $this->findOneBy(['id' => $id, 'branchId' => $branchId, 'userId' => $userId]);
    }

    /**
     * @return list<CreditNoteDraft>
     */
    public function listOwned(string $branchId, int $userId): array
    {
        return $this->findBy(['branchId' => $branchId, 'userId' => $userId], ['updatedAt' => 'DESC'], 50);
    }
}
