<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Domain;

use App\Inventory\Domain\PriceListPricing;
use PHPUnit\Framework\TestCase;

final class PriceListPricingTest extends TestCase
{
    public function testASpecialPriceWinsOverTheFactorAndTheBasePrice(): void
    {
        self::assertSame(
            ['price' => '10.4000', 'source' => 'override'],
            PriceListPricing::resolve('10.10', '1.5', '10.4'),
        );
    }

    public function testTheFactorAppliesToTheBasePriceAndRoundsHalfUpToFourDecimals(): void
    {
        self::assertSame(
            ['price' => '10.7463', 'source' => 'multiplier'],
            PriceListPricing::resolve('10.40', '1.0333', null),
        );
        self::assertSame(
            ['price' => '1.0005', 'source' => 'multiplier'],
            PriceListPricing::resolve('1.00', '1.00045', null),
        );
    }

    public function testWithoutOverrideOrFactorTheBasePriceApplies(): void
    {
        self::assertSame(['price' => '10.1000', 'source' => 'base'], PriceListPricing::resolve('10.10', null, null));
        self::assertSame(['price' => '10.1000', 'source' => 'base'], PriceListPricing::resolve('10.10', '', null));
    }

    public function testAnOverrideOfZeroIsStillAnOverride(): void
    {
        self::assertSame(['price' => '0.0000', 'source' => 'override'], PriceListPricing::resolve('10.10', null, '0'));
    }
}
