<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Sort;
use App\Customer\Domain\CustomerGetAllUseCase;
use App\Customer\Domain\CustomerGetOneUseCase;
use App\Customer\Infrastructure\CustomerEntity;

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
            $context = OptionUtils::addSort($context, new Sort('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, CustomerEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['ruc']);
    }
}