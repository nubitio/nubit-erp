<?php

declare(strict_types=1);

namespace App\State\Purchase;

final readonly class PurchasesWritePolicy
{
    public function __construct(
        public string $mode,
        public bool $writeNewSchema,
        public bool $skipLegacyPersist,
        public bool $strict,
    ) {
    }
}
