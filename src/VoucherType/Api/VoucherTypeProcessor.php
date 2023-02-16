<?php

namespace App\VoucherType\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\VoucherType\Domain\CreateVoucherTypeUseCase;
use App\VoucherType\Domain\DeleteVoucherTypeUseCase;
use App\VoucherType\Domain\UpdateVoucherTypeUseCase;
use App\VoucherType\Domain\VoucherType;

readonly class VoucherTypeProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateVoucherTypeUseCase $createUseCase,
        public UpdateVoucherTypeUseCase $updateUseCase,
        public DeleteVoucherTypeUseCase $deleteUseCase
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?VoucherType
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->id);
            return null;
        }

        if ($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}