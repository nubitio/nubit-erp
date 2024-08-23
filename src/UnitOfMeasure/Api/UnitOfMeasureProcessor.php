<?php

namespace App\UnitOfMeasure\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\UnitOfMeasure\Domain\CreateUnitOfMeasureUseCase;
use App\UnitOfMeasure\Domain\DeleteUnitOfMeasureUseCase;
use App\UnitOfMeasure\Domain\UnitOfMeasure;
use App\UnitOfMeasure\Domain\UpdateUnitOfMeasureUseCase;

/**
 * @implements ApiProcessorInterface<UnitOfMeasure, UnitOfMeasure>
 */
readonly class UnitOfMeasureProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateUnitOfMeasureUseCase $createUseCase,
        public UpdateUnitOfMeasureUseCase $updateUseCase,
        public DeleteUnitOfMeasureUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?UnitOfMeasure
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->unidad_id) {
                $this->deleteUseCase->__invoke($data->unidad_id);
            }

            return null;
        }

        if ($data->unidad_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
