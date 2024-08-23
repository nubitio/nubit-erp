<?php

namespace App\CashboxNew\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\CashboxNew\Domain\Cashbox;
use App\CashboxNew\Domain\CreateCashboxUseCase;
use App\CashboxNew\Domain\DeleteCashboxUseCase;
use App\CashboxNew\Domain\UpdateCashboxUseCase;
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
