<?php

namespace App\Core\Infrastructure\DataLoader;

/**
 * @template T of object
 */
interface DataLoaderInterface
{
    /**
     * @return T
     */
    public function loadCollection(LoadOptionsInterface $options): object;

    /**
     * @return T|null
     */
    public function loadOne(LoadOptionsInterface $options): ?object;
}
