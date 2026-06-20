<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Dto\PriceListApplyDto;
use App\Inventory\State\PriceListApplyProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PriceListApplyProviderTest extends TestCase
{
    /** @param list<array<string,string>> $rows */
    private function makeProvider(array $rows): PriceListApplyProvider
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn($rows);

        return new PriceListApplyProvider($conn);
    }

    public function testProvideReturnsMappedApplyDtos(): void
    {
        $provider = $this->makeProvider([
            ['id' => 'prod-1', 'price' => '90.0000'],
            ['id' => 'prod-2', 'price' => '250.0000'],
        ]);

        $result = $provider->provide(new GetCollection(), ['id' => 5]);

        self::assertIsArray($result);
        self::assertCount(2, $result);

        /** @var PriceListApplyDto $first */
        $first = $result[0];
        self::assertInstanceOf(PriceListApplyDto::class, $first);
        self::assertSame('prod-1', $first->id);
        self::assertSame('90.0000', $first->price);

        /** @var PriceListApplyDto $second */
        $second = $result[1];
        self::assertSame('prod-2', $second->id);
        self::assertSame('250.0000', $second->price);
    }

    public function testProvideReturnsEmptyArrayForUnknownPriceList(): void
    {
        $provider = $this->makeProvider([]);

        $result = $provider->provide(new GetCollection(), ['id' => 999]);

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testProvidePassesPriceListIdToQuery(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::anything(), ['id' => 7])
            ->willReturn([]);

        $provider = new PriceListApplyProvider($conn);
        $provider->provide(new GetCollection(), ['id' => 7]);
    }
}
