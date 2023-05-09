<?php

namespace App\Specialty\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Specialty\Domain\Specialty;
use App\Specialty\Domain\CreateSpecialtyUseCase;
use App\Specialty\Domain\DeleteSpecialtyUseCase;
use App\Specialty\Domain\UpdateSpecialtyUseCase;

/**
 * @implements ApiProcessorInterface<Specialty>
 */
readonly class SpecialtyProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateSpecialtyUseCase $createUseCase,
        public UpdateSpecialtyUseCase $updateUseCase,
        public DeleteSpecialtyUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Specialty
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