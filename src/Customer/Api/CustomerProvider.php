<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Infrastructure\CustomerEntity;
use App\Customer\Domain\CustomerGetAllUseCase;
use App\Customer\Domain\CustomerGetOneUseCase;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

readonly class CustomerProvider implements ProviderInterface
{
    public function __construct(
        private CustomerGetAllUseCase $getAllUseCase,
        private CustomerGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $options = OptionsBuilder::build($context, CustomerEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['ruc']);
    }
}