<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Domain\PriceListPricing;
use App\Inventory\Dto\PriceListApplyDto;
use App\Inventory\Repository\PriceListItemRepository;
use App\Inventory\Repository\PriceListRepository;
use Override;

/**
 * Provider for GET /api/price-lists/{id}/apply.
 *
 * Returns a flat map of { productId → effectivePrice } for all active products
 * so that the till and the sale form can look up the tariff price of each line.
 * The price of each product comes from {@see PriceListPricing}.
 *
 * @implements ProviderInterface<object>
 */
readonly class PriceListApplyProvider implements ProviderInterface
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

        return array_map(
            static fn(array $row): PriceListApplyDto => new PriceListApplyDto(
                id: $row['id'],
                price: PriceListPricing::resolve($row['basePrice'], $multiplier, $row['override'])['price'],
            ),
            $this->items->catalogueWithOverrides((int) $priceList->getId()),
        );
    }
}
