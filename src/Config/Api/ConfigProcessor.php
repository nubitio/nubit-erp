<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Config\Domain\Config;
use App\Config\Domain\ConfigCreateUseCase;
use App\Config\Domain\ConfigDeleteUseCase;
use App\Config\Domain\ConfigGetOneUseCase;
use App\Config\Domain\ConfigUpdateUseCase;

readonly class ConfigProcessor implements ProcessorInterface
{
    public function __construct(
        public ConfigGetOneUseCase $getOneUseCase,
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

        $exist = $this->getOneUseCase->__invoke($data->id);

        if ($exist) {
            $result = $this->ConfigUpdateUseCase->__invoke($data);
        } else {
            $result = $this->ConfigCreateUseCase->__invoke($data);
        }

        return $result;
    }
}