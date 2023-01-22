<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Warehouse\Infrastructure\WarehouseEntity;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseGetOneUseCase;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

readonly class WarehouseProvider implements ProviderInterface
{
    public function __construct(
        private WarehouseGetAllUseCase $getAllUseCase,
        private WarehouseGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $options = OptionsBuilder::build($context, WarehouseEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['almacen_id']);
    }
}