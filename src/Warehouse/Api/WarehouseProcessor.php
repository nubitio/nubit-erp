<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\WarehouseGetOneUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;

readonly class WarehouseProcessor implements ProcessorInterface
{
    public function __construct(
        public WarehouseGetOneUseCase $getOneUseCase,
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

        $exist = $this->getOneUseCase->__invoke($data->almacen_id);

        if ($exist) {
            $result = $this->WarehouseUpdateUseCase->__invoke($data);
        } else {
            $result = $this->WarehouseCreateUseCase->__invoke($data);
        }

        return $result;
    }
}