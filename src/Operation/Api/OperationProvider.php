<?php

namespace App\Operation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Operation\Domain\GetAllOperationUseCase;
use App\Operation\Domain\GetOneOperationUseCase;
use App\Operation\Infrastructure\OperationEntity;

readonly class OperationProvider implements ProviderInterface
{
    public function __construct(
        private GetAllOperationUseCase $getAllUseCase,
        private GetOneOperationUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, OperationEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['operacion_id']);
    }
}