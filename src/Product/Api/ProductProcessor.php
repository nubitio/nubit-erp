<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Product\Domain\CreateProductUseCase;
use App\Product\Domain\DeleteProductUseCase;
use App\Product\Domain\Product;
use App\Product\Domain\UpdateProductUseCase;

readonly class ProductProcessor implements ProcessorInterface
{
    public function __construct(
        public CreateProductUseCase $createUseCase,
        public UpdateProductUseCase $updateUseCase,
        public DeleteProductUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->producto_id);
            return null;
        }

        if ($data->producto_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}