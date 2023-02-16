<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Config\Domain\Config;
use App\Config\Domain\ConfigCreateUseCase;
use App\Config\Domain\ConfigDeleteUseCase;
use App\Config\Domain\ConfigUpdateUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<Config>
 */
readonly class ConfigProcessor implements ApiProcessorInterface
{
    public function __construct(
        public ConfigCreateUseCase $ConfigCreateUseCase,
        public ConfigUpdateUseCase $ConfigUpdateUseCase,
        public ConfigDeleteUseCase $ConfigDeleteUseCase,
    )
    {
    }

    /** @inheritDoc */
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