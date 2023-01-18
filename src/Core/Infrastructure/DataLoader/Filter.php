<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class Filter implements FilterInterface
{
    public function __construct(
        public string          $field,
        public FilterOperation $op,
        public string          $value,
    )
    {
    }
}