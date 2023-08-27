<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Sale\Domain\GetSalesByCustomerIdUseCase;
use App\Sale\Domain\Sale;

/**
 * @implements ApiProviderInterface<Sale>
 */
readonly class SaleByCustomerProvider implements ApiProviderInterface
{
    public function __construct(
        private GetSalesByCustomerIdUseCase $getSalesByCustomerIdUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getSalesByCustomerIdUseCase->__invoke(strval($uriVariables['customer_id']));
        }

        return null;
    }
}
