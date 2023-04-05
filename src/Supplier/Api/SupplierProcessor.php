<?php

namespace App\Supplier\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Supplier\Domain\CreateSupplierUseCase;
use App\Supplier\Domain\DeleteSupplierUseCase;
use App\Supplier\Domain\Supplier;
use App\Supplier\Domain\UpdateSupplierUseCase;

/**
 * @implements ApiProcessorInterface<Supplier>
 */
readonly class SupplierProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateSupplierUseCase $createUseCase,
        public UpdateSupplierUseCase $updateUseCase,
        public DeleteSupplierUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Supplier
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->proveedor_id) {
                $this->deleteUseCase->__invoke($data->proveedor_id);
            }
            return null;
        }

        if ($data->proveedor_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}