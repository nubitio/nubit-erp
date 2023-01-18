<?php

namespace App\Core\Infrastructure\Common\DataLoader;

readonly class Sort
{
    public function __construct(
        public string $field,
        public string $order,
    )
    {
    }
}