<?php

namespace App\PriceList\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\PriceList\Domain\PriceList;
use App\PriceList\Domain\GetAllPriceListUseCase;
use App\PriceList\Domain\GetOnePriceListUseCase;
use App\PriceList\Infrastructure\PriceListEntity;

readonly class PriceListProvider implements ProviderInterface
{
    public function __construct(
        private GetAllPriceListUseCase $getAllUseCase,
        private GetOnePriceListUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PriceListEntity::class, PriceList::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}