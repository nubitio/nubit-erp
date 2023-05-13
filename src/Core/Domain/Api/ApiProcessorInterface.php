<?php

namespace App\Core\Domain\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * @template T of object
 */
interface ApiProcessorInterface extends ProcessorInterface
{
    /**
     * @param T $data
     * @param Operation $operation
     * @param array<string, bool|float|int|string|null> $uriVariables
     * @param array<string, bool|float|int|string|null> $context
     *
     * @return ?T
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): object|null;
}