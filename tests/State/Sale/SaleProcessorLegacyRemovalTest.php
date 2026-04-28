<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Sales\State\SaleProcessor;
use PHPUnit\Framework\TestCase;

final class SaleProcessorLegacyRemovalTest extends TestCase
{
    public function testLegacyCashMovementHelpersAreRemoved(): void
    {
        // createCashMovementForSale and isLegacyCashMovementDisabled were legacy helpers
        // that wrote directly to the old cash_movement table. AC-04 requires their removal:
        // SaleProcessor must no longer write legacy CashMovement rows.
        $reflection = new \ReflectionClass(SaleProcessor::class);

        self::assertFalse($reflection->hasMethod('createCashMovementForSale'));
        self::assertFalse($reflection->hasMethod('isLegacyCashMovementDisabled'));
    }

    public function testConstructorNoLongerDependsOnLegacyCashMovementRepository(): void
    {
        // cashMovementCategoryRepository is fetched via entityManager to avoid
        // adding a constructor dependency — assert it is NOT a constructor param.
        $reflection = new \ReflectionClass(SaleProcessor::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor->getParameters()
        );

        self::assertNotContains('cashMovementCategoryRepository', $parameterNames);
    }

    public function testNegativeStockPolicyNoLongerUsesLegacyCheckStockFlag(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);
        $file = $reflection->getFileName();
        self::assertIsString($file);

        $source = file_get_contents($file);
        self::assertIsString($source);
        self::assertStringNotContainsString('Constants::CHECK_STOCK', $source);
    }

    public function testInlinePurchasesHelpersAreRemoved(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);

        self::assertFalse($reflection->hasMethod('isPurchasesV2WriteEnabled'));
        self::assertFalse($reflection->hasMethod('isPurchasesV2StrictMode'));
    }

    public function testConstructorDoesNotDependOnPurchasesWritePolicyResolver(): void
    {
        $reflection = new \ReflectionClass(SaleProcessor::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor->getParameters()
        );

        self::assertNotContains('purchasesWritePolicyResolver', $parameterNames);
    }
}
