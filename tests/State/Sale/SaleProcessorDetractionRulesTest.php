<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\State\Sale\SaleProcessor;
use PHPUnit\Framework\TestCase;

final class SaleProcessorDetractionRulesTest extends TestCase
{
    public function testDetractionThresholdIsZeroForNoMinimumCodes(): void
    {
        $processor = (new \ReflectionClass(SaleProcessor::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(SaleProcessor::class, 'getDetractionThreshold');
        $method->setAccessible(true);

        self::assertSame(0.0, $method->invoke($processor, '008'));
        self::assertSame(0.0, $method->invoke($processor, '016'));
        self::assertSame(0.0, $method->invoke($processor, '019'));
        self::assertSame(0.0, $method->invoke($processor, '021'));
    }

    public function testDetractionThresholdDefaultsToSevenHundredForOtherCodes(): void
    {
        $processor = (new \ReflectionClass(SaleProcessor::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(SaleProcessor::class, 'getDetractionThreshold');
        $method->setAccessible(true);

        self::assertSame(700.0, $method->invoke($processor, '037'));
        self::assertSame(700.0, $method->invoke($processor, '999'));
    }
}
