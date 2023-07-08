<?php

namespace App\VoucherSeries\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Domain\CreateVoucherSeriesUseCase;
use App\VoucherSeries\Domain\DeleteVoucherSeriesUseCase;
use App\VoucherSeries\Domain\UpdateVoucherSeriesUseCase;

/**
 * @implements ApiProcessorInterface<VoucherSeries>
 */
readonly class VoucherSeriesProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateVoucherSeriesUseCase $createUseCase,
        public UpdateVoucherSeriesUseCase $updateUseCase,
        public DeleteVoucherSeriesUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?VoucherSeries
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }
            return null;
        }

        if($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}