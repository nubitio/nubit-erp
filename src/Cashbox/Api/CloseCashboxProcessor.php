<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\Operation;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\GetOneCashboxUseCase;
use App\Cashbox\Domain\UpdateCashboxUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<CloseCashboxDto, Cashbox>
 */
readonly class CloseCashboxProcessor implements ApiProcessorInterface
{
    public function __construct(
        public GetOneCashboxUseCase $getOneCashboxUseCase,
        public UpdateCashboxUseCase $updateCashboxUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Cashbox
    {
        if ($data->caja_id) {
            $cashBox = $this->getOneCashboxUseCase->__invoke($data->caja_id);

            $cashBox->cierre = new \DateTime();
            $cashBox->estado = 2;

            return $this->updateCashboxUseCase->__invoke($cashBox);
        }

        return null;
    }
}
