<?php

namespace App\Core\Domain\DataLoader;

class JoinRule
{
    public function __construct(
        public string $entity,
        public string $alias,
        public JoinType $type = JoinType::INNER,
        public ?string $condition = null,
    ) {
    }
}
