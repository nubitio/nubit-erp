<?php

namespace App\Operation\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation as ApiPlatformOperation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Operation\Domain\CreateOperationUseCase;
use App\Operation\Domain\DeleteOperationUseCase;
use App\Operation\Domain\Operation;
use App\Operation\Domain\UpdateOperationUseCase;

/**
 * @implements ApiProcessorInterface<Operation>
 */
readonly class OperationProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateOperationUseCase $createUseCase,
        public UpdateOperationUseCase $updateUseCase,
        public DeleteOperationUseCase $deleteUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, ApiPlatformOperation $operation, array $uriVariables = [], array $context = []): ?Operation
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->operacion_id) {
                $this->deleteUseCase->__invoke($data->operacion_id);
            }

            return null;
        }

        if ($data->operacion_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
