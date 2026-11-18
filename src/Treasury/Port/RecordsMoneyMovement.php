<?php

declare(strict_types=1);

namespace App\Treasury\Port;

use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\TreasuryException;
use DateTimeImmutable;

/**
 * The only way other contexts put money into or out of an account. Callers invoke it inside the
 * transaction that persists the payment, so the movement and the payment commit or roll back together.
 *
 * Both methods return null when Treasury is not enabled for the tenant: the caller carries on as it did
 * before Treasury existed.
 */
interface RecordsMoneyMovement
{
    /**
     * @throws TreasuryException when the request breaks a rule (currency, missing drawer, unknown account)
     */
    public function record(MoneyMovementRequest $request): ?MoneyMovementResult;

    /**
     * Undoes a movement with a correcting one, on the same account, never by editing the original. Returns
     * null when there is nothing to undo (the payment was made before Treasury, or with it off).
     *
     * @throws TreasuryException
     */
    public function reverse(
        MovementKey $original,
        MovementKey $reversal,
        DateTimeImmutable $occurredAt,
        ?int $userId,
        ?string $description = null,
    ): ?MoneyMovementResult;
}
