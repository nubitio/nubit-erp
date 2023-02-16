<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Config\Domain\Config;
use App\Config\Domain\ConfigGetAllUseCase;
use App\Config\Domain\ConfigGetOneUseCase;
use App\Config\Domain\GetOneSettingByKeyUseCase;
use App\Config\Infrastructure\ConfigEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

readonly class ConfigProvider implements ApiProviderInterface
{
    public function __construct(
        private ConfigGetAllUseCase       $getAllUseCase,
        private ConfigGetOneUseCase       $getOneUseCase,
        private GetOneSettingByKeyUseCase $getOneByKeyUseCase
    )
    {
    }

    /** @inheritDoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, ConfigEntity::class, Config::class);

            return $this->getAllUseCase->__invoke($options);
        }

        if (isset($uriVariables['name'])) {
            return $this->getOneByKeyUseCase->__invoke($uriVariables['name']);
        }

        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}