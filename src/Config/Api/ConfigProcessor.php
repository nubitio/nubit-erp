<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Config\Domain\Config;
use App\Config\Domain\ConfigCreateUseCase;
use App\Config\Domain\ConfigDeleteUseCase;
use App\Config\Domain\ConfigUpdateUseCase;

readonly class ConfigProcessor implements ProcessorInterface
{
    public function __construct(
        public ConfigCreateUseCase $ConfigCreateUseCase,
        public ConfigUpdateUseCase $ConfigUpdateUseCase,
        public ConfigDeleteUseCase $ConfigDeleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Config
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->ConfigDeleteUseCase->__invoke($data->id);
            return null;
        }

        if ($data->id) {
            return $this->ConfigUpdateUseCase->__invoke($data);
        }

        return $this->ConfigCreateUseCase->__invoke($data);
    }
}