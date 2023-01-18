<?php

namespace App\Core\Infrastructure\DataLoader;

readonly class EasyStore implements DataStoreInterface
{
    /**
     * @template T
     *
     * @param int $total
     * @param T[] $rows
     */
    public function __construct(
        public int   $total,
        public array $rows,
    )
    {
    }
}