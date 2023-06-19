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
    public function load(LoadOptionsInterface $options): object;
}
