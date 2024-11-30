<?php

namespace App\Core\Infrastructure\DataLoader;

use App\Core\Domain\DataLoader\LoadOptions;

/**
 * @template T of object
 */
interface DataLoaderInterface
{
    /**
     * @return T
     */
    public function loadCollection(LoadOptions $options): mixed;

    /**
     * @return T|null
     */
    public function loadOne(LoadOptions $options): ?object;
}
