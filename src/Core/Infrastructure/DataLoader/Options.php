<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class Options
{
    public function __construct(
        public string $entity,
        public string $page,
        public string $rows,
        /** @var Sort[] */
        public array  $sort,
        /** @var FilterInterface[] */
        public array  $filters = [],
    )
    {
    }
}