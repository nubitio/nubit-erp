<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;

/**
 * @implements ApiProcessorInterface<Warehouse>
 */
readonly class WarehouseProcessor implements ApiProcessorInterface
{
    public function __construct(
        public WarehouseCreateUseCase $WarehouseCreateUseCase,
        public WarehouseUpdateUseCase $WarehouseUpdateUseCase,
        public WarehouseDeleteUseCase $WarehouseDeleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Warehouse
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->almacen_id) {
                $this->WarehouseDeleteUseCase->__invoke($data->almacen_id);
            }

            return null;
        }

        if ($data->almacen_id) {
            return $this->WarehouseUpdateUseCase->__invoke($data);
        }

        return $this->WarehouseCreateUseCase->__invoke($data);
    }
}
