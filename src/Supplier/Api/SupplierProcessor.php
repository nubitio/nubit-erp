<?php

namespace App\Supplier\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Supplier\Domain\Supplier;
use App\Supplier\Domain\CreateSupplierUseCase;
use App\Supplier\Domain\DeleteSupplierUseCase;
use App\Supplier\Domain\UpdateSupplierUseCase;

readonly class SupplierProcessor implements ProcessorInterface
{
    public function __construct(
        public CreateSupplierUseCase $createUseCase,
        public UpdateSupplierUseCase $updateUseCase,
        public DeleteSupplierUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Supplier
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->proveedor_id);
            return null;
        }

        if($data->proveedor_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}