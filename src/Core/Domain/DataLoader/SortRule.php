<?php

namespace App\Core\Domain\DataLoader;

readonly class SortRule
{
    public function __construct(
        public string $field,
        public string $order,
    ) {
    }
}
