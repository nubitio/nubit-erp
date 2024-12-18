<?php

namespace App\CashboxDetail\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\CreateCashboxDetailUseCase;
use App\CashboxDetail\Domain\DeleteCashboxDetailUseCase;
use App\CashboxDetail\Domain\UpdateCashboxDetailUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<CashboxDetail, CashboxDetail>
 */
readonly class CashboxDetailProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateCashboxDetailUseCase $createUseCase,
        public UpdateCashboxDetailUseCase $updateUseCase,
        public DeleteCashboxDetailUseCase $deleteUseCase,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?CashboxDetail {
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
