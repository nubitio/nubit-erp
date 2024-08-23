<?php

namespace App\Core\Domain\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * @template T1 of object
 * @template T2 of object
 *
 * @extends ProcessorInterface<T1, T2>
 */
interface ApiProcessorInterface extends ProcessorInterface
{
    /**
     * @param T1                                        $data
     * @param array<string, bool|float|int|string|null> $uriVariables
     * @param array<string, bool|float|int|string|null> $context
     *
     * @return T2
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object;
}
