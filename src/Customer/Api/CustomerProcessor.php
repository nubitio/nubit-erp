<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Customer\Domain\Customer;
use App\Customer\Domain\CustomerCreateUseCase;
use App\Customer\Domain\CustomerDeleteUseCase;
use App\Customer\Domain\CustomerGetOneUseCase;
use App\Customer\Domain\CustomerUpdateUseCase;

/**
 * @implements ApiProcessorInterface<Customer>
 */
readonly class CustomerProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CustomerGetOneUseCase $getOneUseCase,
        public CustomerCreateUseCase $CustomerCreateUseCase,
        public CustomerUpdateUseCase $CustomerUpdateUseCase,
        public CustomerDeleteUseCase $CustomerDeleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Customer
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->CustomerDeleteUseCase->__invoke($data->ruc);
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->ruc);

        if ($exist) {
            $result = $this->CustomerUpdateUseCase->__invoke($data);
        } else {
            $result = $this->CustomerCreateUseCase->__invoke($data);
        }

        return $result;
    }
}