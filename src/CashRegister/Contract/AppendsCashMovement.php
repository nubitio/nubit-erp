<?php

declare(strict_types=1);

namespace App\CashRegister\Contract;

/**
 * What other contexts may do to the drawer ledger (`cash_movements`). It only appends, inside the caller's
 * transaction, and it does not post to the general ledger: the caller already posts its own economic event, so
 * posting here would count the money twice.
 */
interface AppendsCashMovement
{
    /** @throws CashMovementRejected */
    public function append(CashMovementEntry $entry): CashMovementRef;

    public function find(string $idempotencyKey): ?CashMovementRef;
}
