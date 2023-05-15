<?php

namespace App\Physician\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Physician\Domain\CreatePhysicianUseCase;
use App\Physician\Domain\DeletePhysicianUseCase;
use App\Physician\Domain\Physician;
use App\Physician\Domain\UpdatePhysicianUseCase;

/**
 * @implements ApiProcessorInterface<Physician>
 */
readonly class PhysicianProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePhysicianUseCase $createUseCase,
        public UpdatePhysicianUseCase $updateUseCase,
        public DeletePhysicianUseCase $deleteUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Physician
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
