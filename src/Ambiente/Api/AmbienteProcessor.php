<?php

namespace App\Ambiente\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Ambiente\Domain\Ambiente;
use App\Ambiente\Domain\CreateAmbienteUseCase;
use App\Ambiente\Domain\DeleteAmbienteUseCase;
use App\Ambiente\Domain\UpdateAmbienteUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<Ambiente>
 */
readonly class AmbienteProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateAmbienteUseCase $createUseCase,
        public UpdateAmbienteUseCase $updateUseCase,
        public DeleteAmbienteUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Ambiente
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->ambiente_id) {
                $this->deleteUseCase->__invoke($data->ambiente_id);
            }

            return null;
        }

        if ($data->ambiente_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
