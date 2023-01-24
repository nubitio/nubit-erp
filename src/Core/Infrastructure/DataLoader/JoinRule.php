<?php

namespace App\Core\Infrastructure\DataLoader;

class JoinRule
{
    public function __construct(
        public string $entity,
        public string $alias,
        public string $type,
        public string $condition,
    )
    {
    }
}