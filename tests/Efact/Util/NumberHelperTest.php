<?php

declare(strict_types=1);

namespace App\Tests\Efact\Util;

use App\Efact\Util\NumberHelper;
use PHPUnit\Framework\TestCase;

final class NumberHelperTest extends TestCase
{
    public function testFormatDefaultTwoDecimals(): void
    {
        self::assertSame('1,234.56', NumberHelper::format(1234.56));
    }

    public function testFormatWithCustomSeparators(): void
    {
        self::assertSame('1.234,56', NumberHelper::format(1234.56, 2, ',', '.'));
    }

    public function testFormatCurrencyPrependsCurrencyCode(): void
    {
        self::assertSame('PEN 10.00', NumberHelper::formatCurrency(10.0, 'PEN'));
    }

    public function testPercentageCalculation(): void
    {
        self::assertSame(50.00, NumberHelper::percentage(50, 100));
        self::assertSame(33.33, NumberHelper::percentage(1, 3));
    }

    public function testPercentageZeroTotalReturnsZero(): void
    {
        self::assertSame(0.0, NumberHelper::percentage(5, 0));
    }

    public function testClampBelowMin(): void
    {
        self::assertSame(0.0, NumberHelper::clamp(-5.0, 0.0, 100.0));
    }

    public function testClampAboveMax(): void
    {
        self::assertSame(100.0, NumberHelper::clamp(200.0, 0.0, 100.0));
    }

    public function testClampWithinRange(): void
    {
        self::assertSame(50.0, NumberHelper::clamp(50.0, 0.0, 100.0));
    }

    public function testIsEven(): void
    {
        self::assertTrue(NumberHelper::isEven(4));
        self::assertFalse(NumberHelper::isEven(3));
    }

    public function testIsOdd(): void
    {
        self::assertTrue(NumberHelper::isOdd(7));
        self::assertFalse(NumberHelper::isOdd(6));
    }

    public function testRoundHalfUp(): void
    {
        self::assertSame(1.24, NumberHelper::round(1.235, 2));
    }

    public function testCeil(): void
    {
        self::assertSame(1.24, NumberHelper::ceil(1.231, 2));
    }

    public function testFloor(): void
    {
        self::assertSame(1.23, NumberHelper::floor(1.239, 2));
    }

    public function testInRange(): void
    {
        self::assertTrue(NumberHelper::inRange(5.0, 1.0, 10.0));
        self::assertFalse(NumberHelper::inRange(0.0, 1.0, 10.0));
        self::assertFalse(NumberHelper::inRange(11.0, 1.0, 10.0));
    }
}
