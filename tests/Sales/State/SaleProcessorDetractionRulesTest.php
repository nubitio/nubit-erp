<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Sales\State\DetractionCalculator;
use PHPUnit\Framework\TestCase;

final class SaleProcessorDetractionRulesTest extends TestCase
{
    public function testDetractionThresholdIsZeroForNoMinimumCodes(): void
    {
        $calculator = (new \ReflectionClass(DetractionCalculator::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DetractionCalculator::class, 'getThreshold');

        self::assertSame(0.0, $method->invoke($calculator, '008'));
        self::assertSame(0.0, $method->invoke($calculator, '016'));
        self::assertSame(0.0, $method->invoke($calculator, '019'));
        self::assertSame(0.0, $method->invoke($calculator, '021'));
    }

    public function testDetractionThresholdDefaultsToSevenHundredForOtherCodes(): void
    {
        $calculator = (new \ReflectionClass(DetractionCalculator::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DetractionCalculator::class, 'getThreshold');

        self::assertSame(700.0, $method->invoke($calculator, '037'));
        self::assertSame(700.0, $method->invoke($calculator, '999'));
    }
}
