<?php

namespace App\PriceListDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\FilterRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\PriceListDetail\Domain\GetAllPriceListDetailUseCase;
use App\PriceListDetail\Domain\PriceListDetail;
use App\PriceListDetail\Infrastructure\PriceListDetailEntity;

/**
 * @implements ApiProviderInterface<PriceListDetail>
 */
readonly class PriceListDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPriceListDetailUseCase $getAllUseCase
    ) {
    }

    /** {@inheritDoc} */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addFilter($context, new FilterRule('price_list_id', FilterOperation::Equals, intval($uriVariables['id'])));
            $options = OptionsBuilder::build($context, PriceListDetailEntity::class, PriceListDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
