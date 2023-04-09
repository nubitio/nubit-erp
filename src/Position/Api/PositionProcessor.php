<?php

namespace App\Position\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Position\Domain\Position;
use App\Position\Domain\CreatePositionUseCase;
use App\Position\Domain\DeletePositionUseCase;
use App\Position\Domain\UpdatePositionUseCase;

/**
 * @implements ApiProcessorInterface<Position>
 */
readonly class PositionProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePositionUseCase $createUseCase,
        public UpdatePositionUseCase $updateUseCase,
        public DeletePositionUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Position
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