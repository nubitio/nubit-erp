<?php

namespace App\Performance\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Performance\Domain\Performance;
use App\Performance\Domain\CreatePerformanceUseCase;
use App\Performance\Domain\DeletePerformanceUseCase;
use App\Performance\Domain\UpdatePerformanceUseCase;

/**
 * @implements ApiProcessorInterface<Performance>
 */
readonly class PerformanceProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePerformanceUseCase $createUseCase,
        public UpdatePerformanceUseCase $updateUseCase,
        public DeletePerformanceUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Performance
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }
            return null;
        }

        if($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}