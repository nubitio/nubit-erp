<?php

declare(strict_types=1);

namespace App\State\Sale;

final readonly class SalesWritePolicy
{
    public function __construct(
        public string $mode,
        public bool $writeNewSchema,
        public bool $strict,
    ) {
    }
}
