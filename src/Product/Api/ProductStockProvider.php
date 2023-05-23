<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Product\Domain\GetProductStockUseCase;
use App\Product\Domain\ProductStock;

/**
 * @implements ApiProviderInterface<ProductStock>
 */
readonly class ProductStockProvider implements ApiProviderInterface
{
    public function __construct(
        private GetProductStockUseCase $getProductStockUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->getProductStockUseCase->__invoke(strval($uriVariables['producto_id']));
    }
}
