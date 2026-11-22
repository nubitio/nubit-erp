<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Dto\PriceListApplyDto;
use App\Inventory\Entity\PriceList;
use App\Inventory\Repository\PriceListItemRepository;
use App\Inventory\Repository\PriceListRepository;
use App\Inventory\State\PriceListApplyProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PriceListApplyProviderTest extends TestCase
{
    /** @param list<array{id: string, name: string, sku: ?string, code: ?string, basePrice: string, override: ?string}> $rows */
    private function makeProvider(
        array $rows,
        ?string $multiplier = null,
        bool $listExists = true,
    ): PriceListApplyProvider {
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

        return new PriceListApplyProvider($lists, $items);
    }

    public function testProvideReturnsTheEffectivePriceOfEveryProduct(): void
    {
        $result = $this->makeProvider([
            [
                'id' => 'prod-1',
                'name' => 'A',
                'sku' => null,
                'code' => null,
                'basePrice' => '10.10',
                'override' => '10.4000',
            ],
            [
                'id' => 'prod-2',
                'name' => 'B',
                'sku' => null,
                'code' => null,
                'basePrice' => '250.00',
                'override' => null,
            ],
        ])->provide(new GetCollection(), ['id' => 5]);

        self::assertIsArray($result);
        self::assertContainsOnlyInstancesOf(PriceListApplyDto::class, $result);
        self::assertSame(['prod-1', '10.4000'], [$result[0]->id, $result[0]->price]);
        self::assertSame(['prod-2', '250.0000'], [$result[1]->id, $result[1]->price]);
    }

    public function testTheListFactorAppliesWhereThereIsNoSpecialPrice(): void
    {
        $result = $this->makeProvider([
            [
                'id' => 'prod-1',
                'name' => 'A',
                'sku' => null,
                'code' => null,
                'basePrice' => '10.40',
                'override' => null,
            ],
        ], multiplier: '1.0333')->provide(new GetCollection(), ['id' => 5]);

        self::assertIsArray($result);
        /** @var list<PriceListApplyDto> $result */
        self::assertSame('10.7463', $result[0]->price);
    }

    public function testAnUnknownListReturnsNoPrices(): void
    {
        self::assertSame([], $this->makeProvider([], listExists: false)->provide(new GetCollection(), ['id' => 999]));
    }
}
