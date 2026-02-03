<?php

declare(strict_types=1);

namespace App\Tests\Core\Util;

use App\Core\Util\Money;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testMoneyRoundingHalfUp(): void
    {
        $value = Money::money('1.005');
        $this->assertSame('1.01', Money::toString($value));
    }

    public function testRateConversion(): void
    {
        $rate = Money::rate('18');
        $this->assertSame('0.180000', Money::toString($rate, 6));
    }

    public function testMinorConversion(): void
    {
        $value = BigDecimal::of('2.35');
        $this->assertSame(235, Money::toMinor($value));
    }
}
