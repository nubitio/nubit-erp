<?php

declare(strict_types=1);

namespace App\Sales\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Serialises writers of one customer's current account (G-01, G-03).
 *
 * Takes `SELECT … FOR UPDATE` on the customer row. The lock lives until the surrounding transaction
 * ends, so two concurrent ledger appends, or two concurrent uses of the same advance, run one after
 * the other and the second sees what the first wrote. Outside a transaction MariaDB releases the lock
 * at once: callers that need the guarantee must already be inside `wrapInTransaction`.
 *
 * Lock order for every flow: open sales (by id, via the repository) first, then this lock.
 */
readonly class CustomerAccountLock
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function lock(int $customerId): void
    {
        $this->entityManager->getConnection()->fetchOne('SELECT id FROM cliente WHERE id = ? FOR UPDATE', [
            $customerId,
        ]);
    }
}
