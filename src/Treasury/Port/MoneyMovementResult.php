<?php

declare(strict_types=1);

namespace App\Treasury\Port;

use App\Treasury\Domain\AccountKind;

final readonly class MoneyMovementResult
{
    public function __construct(
        public string $movementId,
        public string $accountId,
        public AccountKind $accountKind,
        /** True when the same source had already been recorded; nothing new was written. */
        public bool $duplicate,
        /** Set when the movement landed in suspense because the payment method has no account. */
        public ?string $warning = null,
    ) {}
}
