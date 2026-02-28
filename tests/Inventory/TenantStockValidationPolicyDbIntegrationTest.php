<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Core\Context\TenantContext;
use App\Entity\Product;
use App\Inventory\Core\Service\TenantStockValidationPolicy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class TenantStockValidationPolicyDbIntegrationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS tenant_inventory_policy (
                tenant_id VARCHAR(64) NOT NULL PRIMARY KEY,
                policy VARCHAR(32) NOT NULL
            )'
        );
        $this->connection->executeStatement(
            "DELETE FROM tenant_inventory_policy WHERE tenant_id IN ('default', '901')"
        );
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            "DELETE FROM tenant_inventory_policy WHERE tenant_id IN ('default', '901')"
        );
        $this->connection->close();

        parent::tearDown();
    }

    public function testFallsBackToDefaultPolicyWhenTenantPolicyDoesNotExist(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO tenant_inventory_policy (tenant_id, policy) VALUES (?, ?)',
            ['default', 'WARNING_ONLY']
        );

        $tenantContext = new TenantContext();
        $tenantContext->setTenant(901, 'tenant-int-a', 'tenant-int-a.local', 'req-int-a');

        $policy = new TenantStockValidationPolicy($this->connection, $tenantContext);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '5.000000', '1.000000');

        self::assertTrue($result->allowed);
        self::assertSame('Stock would go negative under WARNING_ONLY', $result->warning);
    }

    public function testUsesTenantSpecificPolicyWhenConfigured(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO tenant_inventory_policy (tenant_id, policy) VALUES (?, ?)',
            ['default', 'WARNING_ONLY']
        );
        $this->connection->executeStatement(
            'INSERT INTO tenant_inventory_policy (tenant_id, policy) VALUES (?, ?)',
            ['901', 'STRICT']
        );

        $tenantContext = new TenantContext();
        $tenantContext->setTenant(901, 'tenant-int-a', 'tenant-int-a.local', 'req-int-a');

        $policy = new TenantStockValidationPolicy($this->connection, $tenantContext);
        $result = $policy->validateSale($this->stockableProduct(), 'wh-1', '5.000000', '1.000000');

        self::assertFalse($result->allowed);
        self::assertSame('Insufficient stock for STRICT policy', $result->reason);
    }

    private function stockableProduct(): Product
    {
        return (new Product())->setType(1);
    }
}
