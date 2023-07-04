<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Customer\Domain\Customer;
use App\Customer\Domain\CustomerGetAllUseCase;
use App\Customer\Domain\CustomerGetOneUseCase;
use App\Customer\Infrastructure\CustomerEntity;

/**
 * @implements ApiProviderInterface<Customer>
 */
readonly class CustomerProvider implements ApiProviderInterface
{
    public function __construct(
        private CustomerGetAllUseCase $getAllUseCase,
        private CustomerGetOneUseCase $getOneUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.price_list', 'pl', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, CustomerEntity::class, Customer::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['cliente_id']));
    }
}
