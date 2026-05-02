<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Core\Service\TenantStockValidationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TenantStockValidationPolicyTest extends TestCase
{
    private function makeProduct(bool $trackInventory = true): Product
    {
        $product = new Product();
        $product->setTrackInventory($trackInventory);

        return $product;
    }

    private function makeService(string $policyValue = ''): TenantStockValidationPolicy
    {
        /** @var SettingsService&MockObject $settings */
        $settings = $this->createStub(SettingsService::class);
        $settings->method('get')
            ->with(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')
            ->willReturn($policyValue);

        return new TenantStockValidationPolicy($settings);
    }

    // ── Non-tracked products always pass ─────────────────────────────────────

    public function testNonTrackedProductIsAlwaysAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT->value);
        $product = $this->makeProduct(trackInventory: false);

        $result = $service->validateSale($product, 'wh-1', '999', '0');

        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
    }

    // ── Sufficient stock always passes regardless of policy ───────────────────

    /** @return array<string, array{string}> */
    public static function allPolicies(): array
    {
        return [
            'STRICT'         => [StockPolicy::STRICT->value],
            'WARNING_ONLY'   => [StockPolicy::WARNING_ONLY->value],
            'ALLOW_NEGATIVE' => [StockPolicy::ALLOW_NEGATIVE->value],
        ];
    }

    #[DataProvider('allPolicies')]
    public function testSufficientStockIsAllowedUnderAnyPolicy(string $policyValue): void
    {
        $service = $this->makeService($policyValue);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '5', '10');

        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
        self::assertNull($result->warning);
    }

    public function testExactStockIsAllowed(): void
    {
        $service = $this->makeService(StockPolicy::STRICT->value);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '10');

        self::assertTrue($result->allowed);
    }

    // ── Insufficient stock — policy-dependent ─────────────────────────────────

    public function testInsufficientStockBlocksUnderStrictPolicy(): void
    {
        $service = $this->makeService(StockPolicy::STRICT->value);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
        self::assertStringContainsString('STRICT', $result->reason ?? '');
    }

    public function testInsufficientStockWarnsUnderWarningOnlyPolicy(): void
    {
        $service = $this->makeService(StockPolicy::WARNING_ONLY->value);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '5');

        self::assertTrue($result->allowed);
        self::assertNotNull($result->warning);
        self::assertStringContainsString('WARNING_ONLY', $result->warning);
    }

    public function testInsufficientStockAllowedUnderAllowNegativePolicy(): void
    {
        $service = $this->makeService(StockPolicy::ALLOW_NEGATIVE->value);
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '100', '1');

        self::assertTrue($result->allowed);
        self::assertNull($result->warning);
        self::assertNull($result->reason);
    }

    // ── Legacy synonym values are accepted ────────────────────────────────────

    public function testLegacyBlockValueMapsToStrict(): void
    {
        $service = $this->makeService('BLOCK');
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
    }

    public function testLegacyAllowAllValueMapsToAllowNegative(): void
    {
        $service = $this->makeService('ALLOW_ALL');
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '100', '1');

        self::assertTrue($result->allowed);
    }

    // ── Empty / unknown config defaults to STRICT ─────────────────────────────

    public function testDefaultsToStrictWhenNoPolicyConfigured(): void
    {
        $service = $this->makeService('');
        $product = $this->makeProduct();

        $result = $service->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
        self::assertStringContainsString('STRICT', $result->reason ?? '');
    }
}
