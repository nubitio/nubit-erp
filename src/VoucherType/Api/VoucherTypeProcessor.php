<?php

namespace App\VoucherType\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\VoucherType\Domain\VoucherType;
use App\VoucherType\Domain\CreateVoucherTypeUseCase;
use App\VoucherType\Domain\DeleteVoucherTypeUseCase;
use App\VoucherType\Domain\UpdateVoucherTypeUseCase;

readonly class VoucherTypeProcessor implements ProcessorInterface
{
    public function __construct(
        public CreateVoucherTypeUseCase $createUseCase,
        public UpdateVoucherTypeUseCase $updateUseCase,
        public DeleteVoucherTypeUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?VoucherType
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->codigo);
            return null;
        }

        if($data->codigo) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}