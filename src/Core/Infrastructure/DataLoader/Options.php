<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class Options
{
    /**
     * @template E of object
     * @template M of object
     *
     * @param class-string<E> $entity
     * @param class-string<M> $mapping
     */
    public function __construct(
        public string $entity,
        public string $mapping,
        public int    $page,
        public int    $rows,
        /** @var JoinRule[] $joins */
        public array  $joins = [],
        /** @var SortRule[] $sort */
        public array  $sort = [],
        /** @var FilterInterface[] $filters */
        public array  $filters = []
    )
    {
    }
}