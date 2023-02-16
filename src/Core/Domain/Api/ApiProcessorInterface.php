<?php

namespace App\Core\Domain\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

interface ApiProcessorInterface extends ProcessorInterface
{
    /**
     * @template T of object
     *
     * @param mixed $data
     * @param Operation $operation
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return ?T
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): object|null;
}