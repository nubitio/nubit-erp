<?php

namespace App\Mesa\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Mesa\Domain\CreateMesaUseCase;
use App\Mesa\Domain\DeleteMesaUseCase;
use App\Mesa\Domain\Mesa;
use App\Mesa\Domain\UpdateMesaUseCase;

/**
 * @implements ApiProcessorInterface<Mesa>
 */
readonly class MesaProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateMesaUseCase $createUseCase,
        public UpdateMesaUseCase $updateUseCase,
        public DeleteMesaUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Mesa
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->mesa_id) {
                $this->deleteUseCase->__invoke($data->mesa_id);
            }

            return null;
        }

        if ($data->mesa_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
