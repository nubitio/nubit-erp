<?php

namespace App\PaymentMethod\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\PaymentMethod\Domain\PaymentMethod;
use App\PaymentMethod\Domain\CreatePaymentMethodUseCase;
use App\PaymentMethod\Domain\DeletePaymentMethodUseCase;
use App\PaymentMethod\Domain\GetOnePaymentMethodUseCase;
use App\PaymentMethod\Domain\UpdatePaymentMethodUseCase;

readonly class PaymentMethodProcessor implements ProcessorInterface
{
    public function __construct(
        public GetOnePaymentMethodUseCase $getOneUseCase,
        public CreatePaymentMethodUseCase $createUseCase,
        public UpdatePaymentMethodUseCase $updateUseCase,
        public DeletePaymentMethodUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PaymentMethod
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->id);
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->id);

        if ($exist) {
            $result = $this->updateUseCase->__invoke($data);
        } else {
            $result = $this->createUseCase->__invoke($data);
        }

        return $result;
    }
}