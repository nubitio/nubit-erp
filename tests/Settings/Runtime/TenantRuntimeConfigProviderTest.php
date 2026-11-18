<?php

declare(strict_types=1);

namespace App\Tests\Settings\Runtime;

use App\Organization\Repository\BranchRepository;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Settings\Contract\TenantWarehousePortInterface;
use App\Settings\Entity\TenantSettings;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetRegistry;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TenantRuntimeConfigProviderTest extends TestCase
{
    public function testAllowsPosPriceEditingForRetail(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY)->getConfig();

        self::assertSame(
            [
                'allowLinePriceOverride' => true,
                'allowCatalogPriceEdit' => true,
                'pieceCountEnabled' => false,
            ],
            $config['ui']['pos'],
        );
    }

    public function testDisablesPosPriceEditingForPharmacy(): void
    {
        $config = $this->createProviderForVertical(PharmacyPreset::KEY)->getConfig();

        self::assertSame(
            [
                'allowLinePriceOverride' => false,
                'allowCatalogPriceEdit' => false,
                'pieceCountEnabled' => false,
            ],
            $config['ui']['pos'],
        );
    }

    public function testExposesThePosPieceCounterOnlyWhenTheTenantTurnedItOn(): void
    {
        $off = $this->createProviderForVertical(RetailPreset::KEY)->getConfig();
        $on = $this->createProviderForVertical(RetailPreset::KEY, pieceCountEnabled: true)->getConfig();

        static::assertFalse($off['ui']['pos']['pieceCountEnabled']);
        static::assertTrue($on['ui']['pos']['pieceCountEnabled']);
    }

    public function testDerivesBasicNavigationTierForSingleLocationTenant(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY)->getConfig();

        self::assertSame('basic', $config['profile']['navigationTier']);
    }

    public function testDerivesStandardNavigationTierForMultiBranchTenant(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY, branchCount: 2)->getConfig();

        self::assertSame('standard', $config['profile']['navigationTier']);
    }

    public function testDerivesStandardNavigationTierForMultiWarehouseEntitlement(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY, entitlements: [
            'multi_warehouse' => ['enabled' => true, 'config' => []],
        ])->getConfig();

        self::assertSame('standard', $config['profile']['navigationTier']);
    }

    public function testDerivesAdvancedNavigationTierForAdvancedReportingEntitlement(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY, entitlements: [
            'advanced_reports' => ['enabled' => true, 'config' => []],
        ])->getConfig();

        self::assertSame('advanced', $config['profile']['navigationTier']);
    }

    public function testKeepsErpCapabilitiesOffByDefault(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY)->getConfig();
        $capabilities = $config['capabilities'];
        self::assertIsArray($capabilities);

        self::assertFalse($capabilities['payroll.core']['enabled']);
        self::assertFalse($capabilities['treasury.bank_reconciliation']['enabled']);
        self::assertFalse($capabilities['fixed_assets.tracking']['enabled']);
        self::assertFalse($capabilities['expense_reports.core']['enabled']);
        self::assertFalse($capabilities['accounting.general_ledger']['enabled']);
        self::assertFalse($capabilities['billing.recurring']['enabled']);
        self::assertTrue($capabilities['sales.core']['enabled']);
        self::assertTrue($capabilities['billing.sunat']['enabled']);
    }

    public function testEnablesMiniErpCapabilitiesFromPlanEntitlements(): void
    {
        $config = $this->createProviderForVertical(RetailPreset::KEY, entitlements: [
            'payroll' => ['enabled' => true, 'config' => []],
            'bank_reconciliation' => ['enabled' => true, 'config' => []],
            'fixed_assets' => ['enabled' => true, 'config' => []],
            'expense_reports' => ['enabled' => true, 'config' => []],
            'general_ledger' => ['enabled' => true, 'config' => []],
            'recurring_billing' => ['enabled' => true, 'config' => []],
        ])->getConfig();
        $capabilities = $config['capabilities'];
        self::assertIsArray($capabilities);
        /** @var array<string, array{enabled: bool}> $capabilities */

        self::assertTrue($capabilities['payroll.core']['enabled']);
        self::assertTrue($capabilities['treasury.bank_reconciliation']['enabled']);
        self::assertTrue($capabilities['fixed_assets.tracking']['enabled']);
        self::assertTrue($capabilities['expense_reports.core']['enabled']);
        self::assertTrue($capabilities['accounting.general_ledger']['enabled']);
        self::assertTrue($capabilities['billing.recurring']['enabled']);
    }

    /**
     * @param array<string, array{enabled: bool, config: array<string, mixed>}> $entitlements
     */
    private function createProviderForVertical(
        string $vertical,
        array $entitlements = [],
        int $branchCount = 1,
        int $warehouseCount = 1,
        bool $pieceCountEnabled = false,
    ): TenantRuntimeConfigProvider {
        $settings = new TenantSettings()
            ->setVertical($vertical)
            ->setPosPieceCountEnabled($pieceCountEnabled);

        /** @var FeatureCheckerInterface&MockObject $featureChecker */
        $featureChecker = $this->createMock(FeatureCheckerInterface::class);
        $featureChecker->method('getEntitlements')->willReturn($entitlements);

        /** @var TenantSettingsRepository&MockObject $settingsRepository */
        $settingsRepository = $this->createMock(TenantSettingsRepository::class);
        $settingsRepository->method('getOrCreate')->willReturn($settings);

        /** @var BranchRepository&MockObject $branchRepository */
        $branchRepository = $this->createMock(BranchRepository::class);
        $branchRepository->method('count')->willReturn($branchCount);
        $branchRepository->method('findOneBy')->willReturn(null);

        $warehousePort = $this->createMock(TenantWarehousePortInterface::class);
        $warehousePort->method('count')->willReturn($warehouseCount);
        $warehousePort->method('defaultWarehouse')->willReturn(null);

        /** @var TenantCapabilityRepository&MockObject $capabilityRepository */
        $capabilityRepository = $this->createMock(TenantCapabilityRepository::class);
        $capabilityRepository->method('allByKey')->willReturn([]);

        return new TenantRuntimeConfigProvider(
            $featureChecker,
            $settingsRepository,
            $branchRepository,
            $warehousePort,
            new VerticalPresetRegistry([new RetailPreset(), new PharmacyPreset()]),
            new TenantCapabilityResolver($featureChecker, $capabilityRepository),
        );
    }
}
