<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Sale\Domain\CreateSaleUseCase;
use App\Sale\Domain\DeleteSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use App\Sale\Domain\UpdateSaleUseCase;

readonly class SaleProcessor implements ApiProcessorInterface
{
    public function __construct(
        public GetOneSaleUseCase $getOneUseCase,
        public CreateSaleUseCase $createUseCase,
        public UpdateSaleUseCase $updateUseCase,
        public DeleteSaleUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Sale
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->movimiento_id);
            return null;
        }

        if ($data->movimiento_id) {
            return $this->updateUseCase->__invoke($data);
        }
        return $this->createUseCase->__invoke($data);
    }
}