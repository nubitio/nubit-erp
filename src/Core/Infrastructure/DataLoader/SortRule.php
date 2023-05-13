<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class SortRule
{
    public function __construct(
        public string $field,
        public string $order,
    ) {
    }
}
