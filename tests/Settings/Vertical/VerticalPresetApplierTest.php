<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

use App\Efact\Enum\StockPolicy;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Settings\Capability\SettingsCapabilitySync;
use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantProfile;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantProfileRepository;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetApplier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class VerticalPresetApplierTest extends TestCase
{
    public function testAppliesSettingsProfileCapabilitiesAndDefaultWarehouse(): void
    {
        $settings = new TenantSettings();
        $settings->setIgv(0.0)->setRequireCashRegister(false)->setAutoInvoice(false);
        $profile = new TenantProfile();

        $branch = new Branch()->setName('Principal');
        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->method('findOneBy')->willReturn($branch);

        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $applier = new VerticalPresetApplier(
            $this->settingsRepo($settings),
            $branchRepo,
            $warehouseRepo,
            $this->profileRepo($profile),
            $this->emptyCapabilityRepo(),
            $this->capabilitySync(),
            $em,
        );
        $result = $applier->apply(new RetailPreset());

        static::assertTrue($result->settingsApplied);
        static::assertTrue($result->defaultWarehouseCreated);
        static::assertSame([], $result->notes);
        static::assertSame(RetailPreset::KEY, $settings->getVertical());
        static::assertSame(DefaultCurrency::PEN, $settings->getDefaultCurrency());
        static::assertSame(StockPolicy::STRICT, $settings->getNegativeStockPolicy());

        // Profile records the applied preset.
        static::assertSame(RetailPreset::KEY, $profile->getVerticalKey());
        static::assertSame(1, $profile->getPresetVersion());
        static::assertNotNull($profile->getAppliedAt());

        // Every capability the preset asks for is enabled from the preset.
        static::assertSame(count(new RetailPreset()->desiredCapabilities()), $result->capabilitiesApplied);
    }

    public function testExistingManualCapabilityOverrideIsNotOverwritten(): void
    {
        $manual = new TenantCapability('cash.sessions');
        $manual->setEnabled(false)->setSource(CapabilitySource::Manual);

        $capabilityRepo = $this->createMock(TenantCapabilityRepository::class);
        $capabilityRepo->method('findByKey')->willReturnCallback(static fn(string $key): ?TenantCapability => $key
            === 'cash.sessions'
                ? $manual
                : null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $applier = new VerticalPresetApplier(
            $this->settingsRepo(new TenantSettings()),
            $this->stubBranchRepo(),
            $this->stubWarehouseRepo(),
            $this->profileRepo(new TenantProfile()),
            $capabilityRepo,
            $this->capabilitySync(),
            $em,
        );
        $result = $applier->apply(new RetailPreset());

        static::assertFalse($manual->isEnabled(), 'manual override must survive a preset re-apply');
        static::assertSame(CapabilitySource::Manual, $manual->getSource());
        // retail asks for 4 capabilities; the manual one is skipped.
        static::assertSame(3, $result->capabilitiesApplied);
    }

    public function testMultiLocationPresetDoesNotCreateDefaultWarehouse(): void
    {
        $pharmacySettings = new TenantSettings();
        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->expects(static::never())->method('count');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $applier = new VerticalPresetApplier(
            $this->settingsRepo($pharmacySettings),
            $this->stubBranchRepo(),
            $warehouseRepo,
            $this->profileRepo(new TenantProfile()),
            $this->emptyCapabilityRepo(),
            $this->capabilitySync(),
            $em,
        );
        $result = $applier->apply(new PharmacyPreset());

        static::assertFalse($result->defaultWarehouseCreated);
        static::assertSame(PharmacyPreset::KEY, $result->presetKey);
        static::assertSame(PharmacyPreset::KEY, $pharmacySettings->getVertical());
    }

    public function testRecordsNoteWhenNoBranchExists(): void
    {
        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->method('findOneBy')->willReturn(null);

        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $applier = new VerticalPresetApplier(
            $this->settingsRepo(new TenantSettings()),
            $branchRepo,
            $warehouseRepo,
            $this->profileRepo(new TenantProfile()),
            $this->emptyCapabilityRepo(),
            $this->capabilitySync(),
            $em,
        );
        $result = $applier->apply(new RetailPreset());

        static::assertFalse($result->defaultWarehouseCreated);
        static::assertCount(1, $result->notes);
    }

    private function capabilitySync(): SettingsCapabilitySync
    {
        $repo = $this->createMock(TenantCapabilityRepository::class);
        $repo->method('findByKey')->willReturn(null);

        return new SettingsCapabilitySync($repo, $this->createMock(EntityManagerInterface::class));
    }

    private function settingsRepo(TenantSettings $settings): TenantSettingsRepository&MockObject
    {
        $repo = $this->createMock(TenantSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        return $repo;
    }

    private function profileRepo(TenantProfile $profile): TenantProfileRepository&MockObject
    {
        $repo = $this->createMock(TenantProfileRepository::class);
        $repo->method('getOrCreate')->willReturn($profile);

        return $repo;
    }

    private function emptyCapabilityRepo(): TenantCapabilityRepository&MockObject
    {
        $repo = $this->createMock(TenantCapabilityRepository::class);
        $repo->method('findByKey')->willReturn(null);

        return $repo;
    }

    private function stubBranchRepo(): BranchRepository&MockObject
    {
        $repo = $this->createMock(BranchRepository::class);
        $repo->method('findOneBy')->willReturn(new Branch()->setName('Principal'));

        return $repo;
    }

    private function stubWarehouseRepo(): WarehouseRepository&MockObject
    {
        $repo = $this->createMock(WarehouseRepository::class);
        $repo->method('count')->willReturn(1);

        return $repo;
    }
}
