<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Dto\PriceListDto;
use App\Inventory\Entity\PriceList;
use App\Inventory\Repository\PriceListItemRepository;
use App\Inventory\Repository\PriceListRepository;
use App\Inventory\State\PriceListProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PriceListProviderTest extends TestCase
{
    /** @param list<array{id: string, name: string, sku: ?string, code: ?string, basePrice: string, override: ?string}> $rows */
    private function makeProvider(array $rows, ?string $multiplier = null, bool $listExists = true): PriceListProvider
    {
        $list = null;
        if ($listExists) {
            $list = new PriceList()
                ->setName('Marcia')
                ->setMultiplier($multiplier);
            new ReflectionProperty(PriceList::class, 'id')->setValue($list, 5);
        }

        $lists = $this->createStub(PriceListRepository::class);
        $lists->method('find')->willReturn($list);
        $items = $this->createStub(PriceListItemRepository::class);
        $items->method('catalogueWithOverrides')->willReturn($rows);

        return new PriceListProvider($lists, $items);
    }

    /** @return array{id: string, name: string, sku: ?string, code: ?string, basePrice: string, override: ?string} */
    private function row(string $id, string $base, ?string $override = null): array
    {
        return [
            'id' => $id,
            'name' => 'Producto ' . $id,
            'sku' => 'SKU-' . $id,
            'code' => null,
            'basePrice' => $base,
            'override' => $override,
        ];
    }

    public function testEachProductReportsItsPriceAndWhereItComesFrom(): void
    {
        $provider = $this->makeProvider([
            $this->row('a', '100.00'),
            $this->row('b', '100.00', '90.0000'),
        ]);

        $result = $provider->provide(new GetCollection(), ['id' => 5]);

        self::assertIsArray($result);
        self::assertContainsOnlyInstancesOf(PriceListDto::class, $result);
        /** @var list<PriceListDto> $result */
        self::assertSame(['a', '100.0000', 'base'], [$result[0]->id, $result[0]->price, $result[0]->priceSource]);
        self::assertSame(['b', '90.0000', 'override'], [$result[1]->id, $result[1]->price, $result[1]->priceSource]);
        self::assertSame('SKU-a', $result[0]->sku);
    }

    public function testAListFactorAppliesToProductsWithoutASpecialPrice(): void
    {
        $result = $this->makeProvider([
            $this->row('a', '100.00'),
            $this->row('b', '100.00', '90.0000'),
        ], multiplier: '0.8')->provide(new GetCollection(), ['id' => 5]);

        self::assertIsArray($result);
        /** @var list<PriceListDto> $result */
        self::assertSame(['80.0000', 'multiplier'], [$result[0]->price, $result[0]->priceSource]);
        self::assertSame(['90.0000', 'override'], [$result[1]->price, $result[1]->priceSource]);
    }

    public function testAnUnknownListHasNoPrices(): void
    {
        self::assertSame(
            [],
            $this->makeProvider([$this->row('a', '1')], listExists: false)->provide(new GetCollection(), ['id' => 999]),
        );
    }
}
