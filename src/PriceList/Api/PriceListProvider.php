<?php

namespace App\PriceList\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\PriceList\Domain\GetAllPriceListUseCase;
use App\PriceList\Domain\GetOnePriceListUseCase;
use App\PriceList\Domain\PriceList;
use App\PriceList\Infrastructure\PriceListEntity;

/**
 * @implements ApiProviderInterface<PriceList>
 */
readonly class PriceListProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPriceListUseCase $getAllUseCase,
        private GetOnePriceListUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PriceListEntity::class, PriceList::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['id']));
    }
}
