<?php

declare(strict_types=1);

namespace App\Tests\Settings\Capability;

use App\Settings\Capability\CapabilityCatalog;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\CapabilityNotAvailableException;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Settings\Entity\TenantCapability;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TenantCapabilityResolverTest extends TestCase
{
    public function testUnknownCapabilityIsClosed(): void
    {
        static::assertFalse($this->resolver()->isEnabled('does.not.exist'));
    }

    public function testDefaultEnabledCapabilityIsOnWithoutRowOrEntitlement(): void
    {
        static::assertTrue($this->resolver()->isEnabled(CapabilityKey::SalesCore));
        static::assertFalse($this->resolver()->isEnabled(CapabilityKey::InventoryMultiWarehouse));
    }

    public function testEntitlementGatesCapability(): void
    {
        static::assertFalse($this->resolver()->isEnabled(CapabilityKey::PayrollCore));
        static::assertTrue($this->resolver(entitlements: ['payroll'])->isEnabled(CapabilityKey::PayrollCore));
    }

    public function testUnmetDependencyClosesCapability(): void
    {
        $stockOff = new TenantCapability('inventory.stock_tracking');
        $stockOff->setEnabled(false)->setSource(CapabilitySource::Manual);

        $resolver = $this->resolver(entitlements: ['multi_warehouse'], rows: [$stockOff]);

        static::assertFalse($resolver->isEnabled(CapabilityKey::InventoryStockTracking));
        static::assertFalse($resolver->isEnabled(CapabilityKey::InventoryMultiWarehouse));
    }

    public function testExplicitRowOverridesTheDefault(): void
    {
        $salesOff = new TenantCapability('sales.core');
        $salesOff->setEnabled(false)->setSource(CapabilitySource::Support);

        static::assertFalse($this->resolver(rows: [$salesOff])->isEnabled(CapabilityKey::SalesCore));
    }

    public function testConfigUsesSafeDefaultsAndDropsUnknownKeys(): void
    {
        $weighted = new TenantCapability('catalog.weighted_items');
        $weighted->setEnabled(true);
        $scale = new TenantCapability('hardware.scale');
        $scale->setEnabled(true)->setConfig(['required' => true, 'bogus' => 1]);

        $resolver = $this->resolver(rows: [$weighted, $scale]);

        static::assertSame(['required' => true], $resolver->config(CapabilityKey::HardwareScale));
        static::assertSame(['autoInvoice' => false], $this->resolver()->config(CapabilityKey::BillingSunat));
        static::assertSame([], $this->resolver()->config(CapabilityKey::PayrollCore));
    }

    public function testRequireThrowsWhenClosed(): void
    {
        $this->expectException(CapabilityNotAvailableException::class);

        $this->resolver()->require(CapabilityKey::PayrollCore);
    }

    public function testRequirePassesWhenOpen(): void
    {
        $resolver = $this->resolver();
        $resolver->require(CapabilityKey::SalesCore);

        static::assertTrue($resolver->isEnabled(CapabilityKey::SalesCore));
    }

    public function testAllReturnsEveryCatalogKey(): void
    {
        $all = $this->resolver()->all();

        static::assertSame(CapabilityCatalog::keys(), array_keys($all));
        static::assertTrue($all['sales.core']['enabled']);
        static::assertSame(['autoInvoice' => false], $all['billing.sunat']['config']);
        static::assertFalse($all['payroll.core']['enabled']);
        static::assertSame([], $all['payroll.core']['config']);
    }

    /**
     * @param list<string> $entitlements feature keys to report as granted
     * @param list<TenantCapability> $rows
     */
    private function resolver(array $entitlements = [], array $rows = []): TenantCapabilityResolver
    {
        $map = [];
        foreach ($entitlements as $key) {
            $map[$key] = ['enabled' => true, 'config' => []];
        }

        /** @var FeatureCheckerInterface&MockObject $featureChecker */
        $featureChecker = $this->createMock(FeatureCheckerInterface::class);
        $featureChecker->method('getEntitlements')->willReturn($map);

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->getCapabilityKey()] = $row;
        }

        /** @var TenantCapabilityRepository&MockObject $repository */
        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository->method('allByKey')->willReturn($byKey);

        return new TenantCapabilityResolver($featureChecker, $repository);
    }
}
