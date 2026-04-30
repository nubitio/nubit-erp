<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Core\Service\TenantStockValidationPolicy;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantStockValidationPolicyTest extends TestCase
{
    private function makeProduct(bool $trackInventory = true): Product
    {
        $product = new Product();
        $product->setTrackInventory($trackInventory);

        return $product;
    }

    private function makePolicy(string|false $policyFromDb, ?string $tenantId = 'tenant-1'): TenantStockValidationPolicy
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn($policyFromDb);

        $context = null;
        if ($tenantId !== null) {
            $context = $this->createStub(TenantContext::class);
            $context->method('getTenantId')->willReturn(1); // int tenant id
        }

        return new TenantStockValidationPolicy($conn, $context);
    }

    // ── Non-tracked products always pass ─────────────────────────────────────

    public function testNonTrackedProductIsAlwaysAllowed(): void
    {
        $policy = $this->makePolicy(StockPolicy::STRICT->value);
        $product = $this->makeProduct(trackInventory: false);

        $result = $policy->validateSale($product, 'wh-1', '999', '0');

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
        $policy = $this->makePolicy($policyValue);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '5', '10');

        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
        self::assertNull($result->warning);
    }

    public function testExactStockIsAllowed(): void
    {
        $policy = $this->makePolicy(StockPolicy::STRICT->value);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '10', '10');

        self::assertTrue($result->allowed);
    }

    // ── Insufficient stock — policy-dependent ─────────────────────────────────

    public function testInsufficientStockBlocksUnderStrictPolicy(): void
    {
        $policy = $this->makePolicy(StockPolicy::STRICT->value);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
        self::assertStringContainsString('STRICT', $result->reason ?? '');
    }

    public function testInsufficientStockWarnsUnderWarningOnlyPolicy(): void
    {
        $policy = $this->makePolicy(StockPolicy::WARNING_ONLY->value);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '10', '5');

        self::assertTrue($result->allowed);
        self::assertNotNull($result->warning);
        self::assertStringContainsString('WARNING_ONLY', $result->warning);
    }

    public function testInsufficientStockAllowedUnderAllowNegativePolicy(): void
    {
        $policy = $this->makePolicy(StockPolicy::ALLOW_NEGATIVE->value);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '100', '1');

        self::assertTrue($result->allowed);
        self::assertNull($result->warning);
        self::assertNull($result->reason);
    }

    // ── Policy fallback chain ─────────────────────────────────────────────────

    public function testDefaultsToStrictWhenNoPolicyInDb(): void
    {
        $policy = $this->makePolicy(false);
        $product = $this->makeProduct();

        $result = $policy->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
        self::assertStringContainsString('STRICT', $result->reason ?? '');
    }

    public function testWorksWithoutTenantContext(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn(false);

        $policy = new TenantStockValidationPolicy($conn, null);
        $product = $this->makeProduct();

        // Without tenant context defaults to STRICT
        $result = $policy->validateSale($product, 'wh-1', '10', '5');

        self::assertFalse($result->allowed);
    }
}
