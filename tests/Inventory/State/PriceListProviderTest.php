<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Dto\PriceListDto;
use App\Inventory\State\PriceListProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PriceListProviderTest extends TestCase
{
    /** @param list<array<string,string>> $rows */
    private function makeProvider(array $rows): PriceListProvider
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn($rows);

        return new PriceListProvider($conn);
    }

    public function testProvideReturnsMappedDtos(): void
    {
        $provider = $this->makeProvider([
            ['id' => 'prod-1', 'name' => 'Producto A', 'sku' => 'SKU-A', 'code' => 'COD-A', 'price' => '100.0000', 'price_source' => 'base'],
            ['id' => 'prod-2', 'name' => 'Producto B', 'sku' => null, 'code' => 'COD-B', 'price' => '90.0000', 'price_source' => 'multiplier'],
            ['id' => 'prod-3', 'name' => 'Producto C', 'sku' => 'SKU-C', 'code' => null, 'price' => '75.0000', 'price_source' => 'override'],
        ]);

        $result = $provider->provide(new GetCollection(), ['id' => 1]);

        self::assertIsArray($result);
        self::assertCount(3, $result);

        /** @var PriceListDto $first */
        $first = $result[0];
        self::assertInstanceOf(PriceListDto::class, $first);
        self::assertSame('prod-1', $first->id);
        self::assertSame('Producto A', $first->name);
        self::assertSame('SKU-A', $first->sku);
        self::assertSame('COD-A', $first->code);
        self::assertSame('100.0000', $first->price);
        self::assertSame('base', $first->priceSource);

        /** @var PriceListDto $second */
        $second = $result[1];
        self::assertSame('multiplier', $second->priceSource);

        /** @var PriceListDto $third */
        $third = $result[2];
        self::assertSame('override', $third->priceSource);
    }

    public function testProvideReturnsEmptyArrayWhenNoActiveProducts(): void
    {
        $provider = $this->makeProvider([]);

        $result = $provider->provide(new GetCollection(), ['id' => 99]);

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testProvidePassesPriceListIdToQuery(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::anything(), ['id' => 42])
            ->willReturn([]);

        $provider = new PriceListProvider($conn);
        $provider->provide(new GetCollection(), ['id' => 42]);
    }
}
