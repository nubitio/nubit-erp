<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use PHPUnit\Framework\TestCase;

final class TenantResourceCatalogTest extends TestCase
{
    public function testInvoicesSupportLegacyAndNewTables(): void
    {
        $catalog = new TenantResourceCatalog();

        $this->assertSame(
            ['tax_document'],
            $catalog->tablesForResource('invoices')
        );
    }

    public function testAllowedPlanKeysContainStorageAndCoreResources(): void
    {
        $catalog = new TenantResourceCatalog();

        $keys = $catalog->allowedPlanLimitKeys();
        $this->assertContains('users', $keys);
        $this->assertContains('customers', $keys);
        $this->assertContains('products', $keys);
        $this->assertContains('invoices', $keys);
        $this->assertContains('branches', $keys);
        $this->assertContains('storage_mb', $keys);
    }
}
