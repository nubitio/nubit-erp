<?php

namespace App\PaymentMethod\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\PaymentMethod\Domain\CreatePaymentMethodUseCase;
use App\PaymentMethod\Domain\DeletePaymentMethodUseCase;
use App\PaymentMethod\Domain\PaymentMethod;
use App\PaymentMethod\Domain\UpdatePaymentMethodUseCase;

/**
 * @implements ApiProcessorInterface<PaymentMethod>
 */
readonly class PaymentMethodProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePaymentMethodUseCase $createUseCase,
        public UpdatePaymentMethodUseCase $updateUseCase,
        public DeletePaymentMethodUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PaymentMethod
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }
            return null;
        }

        if ($data->id) {
            $result = $this->updateUseCase->__invoke($data);
        } else {
            $result = $this->createUseCase->__invoke($data);
        }

        return $result;
    }
}