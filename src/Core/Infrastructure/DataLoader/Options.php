<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class Options
{
    public function __construct(
        public string $entity,
        public string $page,
        public string $rows,
        /** @var JoinRule[] $joins */
        public array  $joins = [],
        /** @var Sort[] $sort */
        public array  $sort = [],
        /** @var FilterInterface[] $filters */
        public array  $filters = []
    )
    {
    }
}