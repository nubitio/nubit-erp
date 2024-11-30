<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Warehouse\Domain\GetAllByBranchUseCase;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseGetOneUseCase;
use App\Warehouse\Infrastructure\WarehouseEntity;

/**
 * @implements ApiProviderInterface<Warehouse>
 */
readonly class WarehouseProvider implements ApiProviderInterface
{
    public function __construct(
        private WarehouseGetAllUseCase $getAllUseCase,
        private GetAllByBranchUseCase $getAllByBranchUseCase,
        private WarehouseGetOneUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            if (isset($uriVariables['local_id'])) {
                return $this->getAllByBranchUseCase->__invoke(strval($uriVariables['local_id']));
            }
            $context = OptionUtils::addJoin($context, new JoinRule('e.local', 'l'));
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, WarehouseEntity::class, Warehouse::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['almacen_id']));
    }
}
