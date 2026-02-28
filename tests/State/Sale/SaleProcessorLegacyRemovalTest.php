<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\State\Sale\SaleProcessor;
use PHPUnit\Framework\TestCase;

final class SaleProcessorLegacyRemovalTest extends TestCase
{
    public function testLegacyCashMovementHelpersAreRemoved(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);

        self::assertFalse($reflection->hasMethod('createCashMovementForSale'));
        self::assertFalse($reflection->hasMethod('isLegacyCashMovementDisabled'));
    }

    public function testConstructorNoLongerDependsOnLegacyCashMovementRepository(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor->getParameters()
        );

        self::assertNotContains('cashMovementCategoryRepository', $parameterNames);
    }
}
