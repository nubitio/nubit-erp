<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CreateCashboxUseCase;
use App\Cashbox\Domain\DeleteCashboxUseCase;
use App\Cashbox\Domain\UpdateCashboxUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<Cashbox, Cashbox>
 */
readonly class CashboxProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateCashboxUseCase $createUseCase,
        public UpdateCashboxUseCase $updateUseCase,
        public DeleteCashboxUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Cashbox
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->caja_id) {
                $this->deleteUseCase->__invoke($data->caja_id);
            }

            return null;
        }

        if ($data->caja_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
