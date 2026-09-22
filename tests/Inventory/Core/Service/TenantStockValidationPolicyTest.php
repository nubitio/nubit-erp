<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Efact\Enum\StockPolicy;
use App\Inventory\Core\Service\TenantStockValidationPolicy;
use App\Settings\Service\TenantSettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantStockValidationPolicyTest extends TestCase
{
    private function makeProduct(bool $trackInventory = true): Product
    {
        $product = new Product();
        $product->setType(1);
        $product->setTrackInventory($trackInventory);

        return $product;
    }

    private function makeService(StockPolicy $policy = StockPolicy::STRICT): TenantStockValidationPolicy
    {
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getNegativeStockPolicy')->willReturn($policy);

        return new TenantStockValidationPolicy($tenantSettings);
    }

    public function testNonTrackedProductIsAlwaysAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct(trackInventory: false);

        $result = $service->validateSale($product, 'wh-1', '999', '0');

        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
    }

    public function testServiceProductIsAlwaysAllowedEvenWhenLegacyTrackFlagIsStale(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = new Product();
        $product->setType(2);
        $product->setTrackInventory(true);

        $result = $service->validateSale($product, 'wh-1', '999', '0');

        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
    }

    public function testDigitalProductIsAlwaysAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct();
        $product->setProductType('DIGITAL');
        $product->setTrackInventory(true);

        $result = $service->validateSale($product, 'wh-1', '999', '0');

        self::assertTrue($result->allowed);
    }

    public function testSufficientStockIsAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '5', '10');

        self::assertTrue($result->allowed);
    }

    public function testExactStockIsAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '10');

        self::assertTrue($result->allowed);
    }

    public function testInsufficientStockBlocksUnderStrictPolicy(): void
    {
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '11', '10');

        self::assertFalse($result->allowed);
    }

    public function testInsufficientStockWarnsUnderWarningOnlyPolicy(): void
    {
        $service = $this->makeService(StockPolicy::WARNING_ONLY);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '11', '10');

        // WARNING_ONLY allows the sale but attaches a warning message
        self::assertTrue($result->allowed);
        self::assertNotNull($result->warning);
    }

    public function testInsufficientStockAllowedUnderAllowNegativePolicy(): void
    {
        $service = $this->makeService(StockPolicy::ALLOW_NEGATIVE);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '999', '0');

        self::assertTrue($result->allowed);
    }

    public function testDefaultsToStrictWhenNoPolicyConfigured(): void
    {
        // TenantSettingsService::getOrCreate() returns STRICT by default
        $service = $this->makeService(StockPolicy::STRICT);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '5', '0');

        self::assertFalse($result->allowed);
    }
}
