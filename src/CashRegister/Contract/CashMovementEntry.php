<?php

declare(strict_types=1);

namespace App\CashRegister\Contract;

use DateTimeImmutable;

/**
 * A cash movement another context asks the drawer ledger to append. The amount is always positive; `isInflow`
 * says whether cash came in (`CASH_IN`) or went out (`CASH_OUT`). The idempotency key is a uuid: appending the
 * same key twice returns the first row.
 */
final readonly class CashMovementEntry
{
    public function __construct(
        public int $sessionId,
        public bool $isInflow,
        public string $amount,
        /** Code of a `cash_movement_category`, e.g. `supplier_payment`. */
        public string $categoryCode,
        public string $description,
        public int $userId,
        public string $idempotencyKey,
        public ?int $paymentMethodId = null,
        public ?DateTimeImmutable $occurredAt = null,
    ) {}
}
