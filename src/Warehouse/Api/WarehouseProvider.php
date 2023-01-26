<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Warehouse\Domain\GetAllByBranchUseCase;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseGetOneUseCase;
use App\Warehouse\Infrastructure\WarehouseEntity;

readonly class WarehouseProvider implements ProviderInterface
{
    public function __construct(
        private WarehouseGetAllUseCase $getAllUseCase,
        private GetAllByBranchUseCase  $getAllByBranchUseCase,
        private WarehouseGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            if (isset($uriVariables['local_id'])) {
                return $this->getAllByBranchUseCase->__invoke($uriVariables['local_id']);
            }
            $context = OptionUtils::addJoin($context, new JoinRule('e.local', 'l'));
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, WarehouseEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['almacen_id']);
    }
}