<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Inventory\Core\Service\UnitConversionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class UnitConversionServiceTest extends TestCase
{
    public function testToBaseConvertsWithConfiguredFactor(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('150.000000');

        $service = new UnitConversionService($connection);

        self::assertSame('300.000000', $service->toBase('prod-1', 10, '2.000000'));
    }

    public function testFromBaseConvertsBackWithConfiguredFactor(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('150.000000');

        $service = new UnitConversionService($connection);

        self::assertSame('2.000000', $service->fromBase('prod-1', 10, '300.000000'));
    }
}
