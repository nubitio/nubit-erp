<?php

namespace App\Core\Domain\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * @template T of object
 *
 * @extends ProviderInterface<T>
 */
interface ApiProviderInterface extends ProviderInterface
{
    /**
     * @param array<string, bool|float|int|string|null>                $uriVariables
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return T|iterable<T>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null;
}
