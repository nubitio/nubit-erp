<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Inventory\Core\Contract\UnitConversionServiceInterface;
use App\Inventory\Core\Service\UnitConversionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class UnitConversionServiceTest extends TestCase
{
    private function makeService(string|false $factorResult): UnitConversionService
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn($factorResult);

        return new UnitConversionService($conn);
    }

    public function testToBaseMultipliesQtyByFactor(): void
    {
        $service = $this->makeService('2.000000'); // 1 unit = 2 base

        $result = $service->toBase('prod-1', 5, '3');

        self::assertSame('6.000000', $result);
    }

    public function testToBaseHandlesDecimalFactor(): void
    {
        $service = $this->makeService('0.500000'); // 1 unit = 0.5 base (e.g. half-dozen)

        $result = $service->toBase('prod-1', 5, '4');

        self::assertSame('2.000000', $result);
    }

    public function testFromBaseDividesByFactor(): void
    {
        $service = $this->makeService('2.000000');

        $result = $service->fromBase('prod-1', 5, '6');

        self::assertSame('3.000000', $result);
    }

    public function testFromBaseHandlesFractionalResult(): void
    {
        $service = $this->makeService('3.000000');

        $result = $service->fromBase('prod-1', 5, '1');

        self::assertSame('0.333333', $result);
    }

    public function testToBaseThrowsWhenNoFactorFound(): void
    {
        $service = $this->makeService(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No conversion configured/');

        $service->toBase('prod-x', 99, '1');
    }

    public function testFromBaseThrowsWhenNoFactorFound(): void
    {
        $service = $this->makeService(false);

        $this->expectException(\RuntimeException::class);
        $service->fromBase('prod-x', 99, '1');
    }

    public function testFromBaseThrowsOnZeroFactor(): void
    {
        $service = $this->makeService('0');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid unit conversion factor/');

        $service->fromBase('prod-1', 5, '10');
    }

    public function testImplementsInterface(): void
    {
        $service = $this->makeService('1.000000');
        self::assertInstanceOf(UnitConversionServiceInterface::class, $service);
    }
}
