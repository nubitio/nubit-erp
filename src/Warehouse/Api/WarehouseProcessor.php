<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;

readonly class WarehouseProcessor implements ProcessorInterface
{
    public function __construct(
        public WarehouseCreateUseCase $WarehouseCreateUseCase,
        public WarehouseUpdateUseCase $WarehouseUpdateUseCase,
        public WarehouseDeleteUseCase $WarehouseDeleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Warehouse
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->WarehouseDeleteUseCase->__invoke($data->almacen_id);
            return null;
        }

        if ($data->almacen_id) {
            return $this->WarehouseUpdateUseCase->__invoke($data);
        }

        return $this->WarehouseCreateUseCase->__invoke($data);
    }
}