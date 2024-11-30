<?php

namespace App\Core\Domain\DataLoader;

readonly class LoadOptions
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
        public ?int $skip = null,
        public ?int $take = null,
        /** @var JoinRule[] $joins */
        public array $joins = [],
        /** @var SortRule[] $sort */
        public array $sort = [],
        /** @var FilterRule[] $filters */
        public array $filters = [],
    ) {
    }
}
