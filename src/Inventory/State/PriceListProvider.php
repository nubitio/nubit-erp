<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Domain\PriceListPricing;
use App\Inventory\Dto\PriceListDto;
use App\Inventory\Repository\PriceListItemRepository;
use App\Inventory\Repository\PriceListRepository;
use Override;

/**
 * Returns all active products with their effective price for a given price list,
 * and where each price comes from (see {@see PriceListPricing} for the rule).
 *
 * @implements ProviderInterface<object>
 */
readonly class PriceListProvider implements ProviderInterface
{
    public function __construct(
        private PriceListRepository $priceLists,
        private PriceListItemRepository $items,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $priceList = $this->priceLists->find((int) $uriVariables['id']);
        if ($priceList === null) {
            return [];
        }

        $multiplier = $priceList->getMultiplier();

        return array_map(static function (array $row) use ($multiplier): PriceListDto {
            $resolved = PriceListPricing::resolve($row['basePrice'], $multiplier, $row['override']);

            return new PriceListDto(
                id: $row['id'],
                name: $row['name'],
                sku: $row['sku'],
                code: $row['code'],
                price: $resolved['price'],
                priceSource: $resolved['source'],
            );
        }, $this->items->catalogueWithOverrides((int) $priceList->getId()));
    }
}
