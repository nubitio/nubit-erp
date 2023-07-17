<?php

namespace App\Orden\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Orden\Domain\CreateOrdenUseCase;
use App\Orden\Domain\DeleteOrdenUseCase;
use App\Orden\Domain\Orden;
use App\Orden\Domain\UpdateOrdenUseCase;

/**
 * @implements ApiProcessorInterface<Orden>
 */
readonly class OrdenProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateOrdenUseCase $createUseCase,
        public UpdateOrdenUseCase $updateUseCase,
        public DeleteOrdenUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Orden
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->orden_id) {
                $this->deleteUseCase->__invoke($data->orden_id);
            }

            return null;
        }

        if ($data->orden_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
