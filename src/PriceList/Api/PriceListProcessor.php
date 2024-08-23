<?php

namespace App\PriceList\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\PriceList\Domain\CreatePriceListUseCase;
use App\PriceList\Domain\DeletePriceListUseCase;
use App\PriceList\Domain\PriceList;
use App\PriceList\Domain\UpdatePriceListUseCase;

/**
 * @implements ApiProcessorInterface<PriceList, PriceList>
 */
readonly class PriceListProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePriceListUseCase $createUseCase,
        public UpdatePriceListUseCase $updateUseCase,
        public DeletePriceListUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PriceList
    {
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
