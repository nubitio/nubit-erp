<?php

namespace App\Production\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Production\Domain\CreateProductionUseCase;
use App\Production\Domain\DeleteProductionUseCase;
use App\Production\Domain\Production;
use App\Production\Domain\UpdateProductionUseCase;

/**
 * @implements ApiProcessorInterface<Production>
 */
readonly class ProductionProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateProductionUseCase $createUseCase,
        public UpdateProductionUseCase $updateUseCase,
        public DeleteProductionUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Production
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }

            return null;
        }

        if ($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
