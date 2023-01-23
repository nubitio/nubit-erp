<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Sale\Domain\Sale;
use App\Sale\Domain\CreateSaleUseCase;
use App\Sale\Domain\DeleteSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\UpdateSaleUseCase;

readonly class SaleProcessor implements ProcessorInterface
{
    public function __construct(
        public GetOneSaleUseCase $getOneUseCase,
        public CreateSaleUseCase $createUseCase,
        public UpdateSaleUseCase $updateUseCase,
        public DeleteSaleUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Sale
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->movimiento_id);
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->movimiento_id);

        if ($exist) {
            $result = $this->updateUseCase->__invoke($data);
        } else {
            $result = $this->createUseCase->__invoke($data);
        }

        return $result;
    }
}