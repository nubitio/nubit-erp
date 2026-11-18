<?php

declare(strict_types=1);

namespace App\Treasury\Port;

use App\Treasury\Domain\TreasuryException;

/**
 * The only way other contexts put money into or out of an account. Callers invoke it inside the
 * transaction that persists the payment, so the movement and the payment commit or roll back together.
 */
interface RecordsMoneyMovement
{
    /**
     * @throws TreasuryException when the request breaks a rule (currency, missing drawer, unknown account)
     */
    public function record(MoneyMovementRequest $request): MoneyMovementResult;
}
