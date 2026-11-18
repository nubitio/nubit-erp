<?php

declare(strict_types=1);

namespace App\CashRegister\Contract;

final readonly class CashMovementRef
{
    public function __construct(
        public int $id,
        public bool $isInflow,
        public string $amount,
        /** True when this call found a row that already existed instead of writing a new one. */
        public bool $duplicate = false,
    ) {}
}
