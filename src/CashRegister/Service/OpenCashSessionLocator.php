<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Doctrine\DBAL\Connection;

/**
 * Finds the cash session a user currently has open in the branch they work on. The POS state and the
 * shift's sales both answer from it, so they can never disagree about which shift is "the" shift: one
 * that ignored the branch would hand back a session the rest of the POS cannot act on (closing it is
 * scoped to the current branch and would answer 404).
 */
readonly class OpenCashSessionLocator
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array{id: int|string, cash_register_id: int|string, name: string, opened_at: string, opening_amount: string}|null
     */
    public function locate(int $userId, ?string $branchId): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT cs.id, cs.cash_register_id, cr.name, cs.opened_at, cs.opening_amount
             FROM cash_sessions cs
             JOIN cash_register cr ON cr.id = cs.cash_register_id
             WHERE cs.opened_by = ? AND cs.status = 'OPEN'"
            . ($branchId === null ? '' : ' AND cr.branch_id = ?')
            . '
             ORDER BY cs.opened_at DESC, cs.id DESC
             LIMIT 1',
            $branchId === null ? [$userId] : [$userId, $branchId],
        );

        /** @var array{id: int|string, cash_register_id: int|string, name: string, opened_at: string, opening_amount: string}|false $row */
        return $row === false ? null : $row;
    }
}
