<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Config\Infrastructure\ConfigEntity;
use App\Config\Domain\ConfigGetAllUseCase;
use App\Config\Domain\ConfigGetOneUseCase;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

readonly class ConfigProvider implements ProviderInterface
{
    public function __construct(
        private ConfigGetAllUseCase $getAllUseCase,
        private ConfigGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $options = OptionsBuilder::build($context, ConfigEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}