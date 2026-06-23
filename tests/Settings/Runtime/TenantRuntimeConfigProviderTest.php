<?php

declare(strict_types=1);

namespace App\Tests\Settings\Runtime;

use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Entity\TenantSettings;
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

        self::assertSame([
            'allowLinePriceOverride' => true,
            'allowCatalogPriceEdit' => true,
        ], $config['ui']['pos']);
    }

    public function testDisablesPosPriceEditingForPharmacy(): void
    {
        $config = $this->createProviderForVertical(PharmacyPreset::KEY)->getConfig();

        self::assertSame([
            'allowLinePriceOverride' => false,
            'allowCatalogPriceEdit' => false,
        ], $config['ui']['pos']);
    }

    private function createProviderForVertical(string $vertical): TenantRuntimeConfigProvider
    {
        $settings = (new TenantSettings())->setVertical($vertical);

        /** @var FeatureCheckerInterface&MockObject $featureChecker */
        $featureChecker = $this->createMock(FeatureCheckerInterface::class);
        $featureChecker->method('getEntitlements')->willReturn([]);

        /** @var TenantSettingsRepository&MockObject $settingsRepository */
        $settingsRepository = $this->createMock(TenantSettingsRepository::class);
        $settingsRepository->method('getOrCreate')->willReturn($settings);

        /** @var BranchRepository&MockObject $branchRepository */
        $branchRepository = $this->createMock(BranchRepository::class);
        $branchRepository->method('count')->willReturn(1);
        $branchRepository->method('findOneBy')->willReturn(null);

        /** @var WarehouseRepository&MockObject $warehouseRepository */
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('count')->willReturn(1);
        $warehouseRepository->method('findOneBy')->willReturn(null);

        return new TenantRuntimeConfigProvider(
            $featureChecker,
            $settingsRepository,
            $branchRepository,
            $warehouseRepository,
            new VerticalPresetRegistry([new RetailPreset(), new PharmacyPreset()]),
        );
    }
}
