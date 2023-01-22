<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Domain\Customer;
use App\Customer\Domain\CustomerCreateUseCase;
use App\Customer\Domain\CustomerDeleteUseCase;
use App\Customer\Domain\CustomerGetOneUseCase;
use App\Customer\Domain\CustomerUpdateUseCase;

readonly class CustomerProcessor implements ProcessorInterface
{
    public function __construct(
        public CustomerGetOneUseCase $getOneUseCase,
        public CustomerCreateUseCase $CustomerCreateUseCase,
        public CustomerUpdateUseCase $CustomerUpdateUseCase,
        public CustomerDeleteUseCase $CustomerDeleteUseCase,
    )
    {
    }

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