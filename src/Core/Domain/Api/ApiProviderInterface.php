<?php

namespace App\Core\Domain\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * @template T of object
 * @extends ProviderInterface<T>
 */
interface ApiProviderInterface extends ProviderInterface
{
    /**
     * @param Operation $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return T|iterable<T>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null;
}