<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Service\TenantStockValidationPolicy;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TenantStockValidationPolicyTest extends TestCase
{
    public function testStrictPolicyBlocksNegativeStock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('STRICT');

        $policy = new TenantStockValidationPolicy($connection);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '3.000000', '1.000000');

        self::assertFalse($result->allowed);
        self::assertSame('Insufficient stock for STRICT policy', $result->reason);
    }

    public function testWarningOnlyPolicyAllowsWithWarning(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('WARNING_ONLY');

        $policy = new TenantStockValidationPolicy($connection);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '3.000000', '1.000000');

        self::assertTrue($result->allowed);
        self::assertSame('Stock would go negative under WARNING_ONLY', $result->warning);
    }

    public function testAllowNegativePolicyAllows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('ALLOW_NEGATIVE');

        $policy = new TenantStockValidationPolicy($connection);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '3.000000', '1.000000');

        self::assertTrue($result->allowed);
        self::assertNull($result->warning);
    }

    public function testServiceProductSkipsStockValidation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchOne');

        $policy = new TenantStockValidationPolicy($connection);
        $result = $policy->validateSale($this->serviceProduct(), 'wh-1', '999.000000', '0.000000');

        self::assertTrue($result->allowed);
    }

    public function testFallsBackToStrictWhenPolicyIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $policy = new TenantStockValidationPolicy($connection);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '3.000000', '1.000000');

        self::assertFalse($result->allowed);
        self::assertSame('Insufficient stock for STRICT policy', $result->reason);
    }

    private function stockableProduct(): Product
    {
        $product = new Product();
        $product->setType(1);

        return $product;
    }

    private function serviceProduct(): Product
    {
        $product = new Product();
        $product->setType(2);

        return $product;
    }
}
