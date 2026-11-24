<?php

declare(strict_types=1);

namespace App\Treasury\Port;

use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\Party;
use DateTimeImmutable;

/**
 * What a caller wants recorded. The amount is positive; `direction` says whether money came in or went out.
 * `key` (source, id, leg) is the idempotency key: recording the same event twice (an offline POS
 * replay, a retried request) returns the first movement instead of writing a second one.
 */
final readonly class MoneyMovementRequest
{
    public function __construct(
        public MovementKey $key,
        public Direction $direction,
        public string $amount,
        public string $currency,
        public string $branchId,
        public DateTimeImmutable $occurredAt,
        public bool $isCash = false,
        public ?int $paymentMethodId = null,
        public ?int $userId = null,
        public ?Party $party = null,
        public ?string $reference = null,
        public ?string $description = null,
        /** Explicit account chosen at payment time; wins over the payment-method mapping. */
        public ?string $accountId = null,
        public string $origin = 'live',
        /** External collections must use the branch's bank mapping, never a drawer or suspense account. */
        public bool $requireMappedBankAccount = false,
    ) {}
}
