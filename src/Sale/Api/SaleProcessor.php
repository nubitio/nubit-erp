<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Sale\Domain\CreateSaleHmsUseCase;
use App\Sale\Domain\CreateSaleUseCase;
use App\Sale\Domain\DeleteSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use App\Sale\Domain\UpdateSaleUseCase;

/**
 * @implements ApiProcessorInterface<Sale>
 */
readonly class SaleProcessor implements ApiProcessorInterface
{
    public function __construct(
        public GetOneSaleUseCase $getOneUseCase,
        public CreateSaleUseCase $createUseCase,
        public CreateSaleHmsUseCase $createHmsUseCase,
        public UpdateSaleUseCase $updateUseCase,
        public DeleteSaleUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Sale
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->movimiento_id) {
                $this->deleteUseCase->__invoke($data->movimiento_id);
            }

            return null;
        }

        if ($data->movimiento_id) {
            return $this->updateUseCase->__invoke($data);
        }

        if ($data instanceof SaleHmsDto) {
            return $this->createHmsUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
