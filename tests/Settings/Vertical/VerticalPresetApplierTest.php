<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\SingleStoreRetailPreset;
use App\Settings\Vertical\VerticalPresetApplier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class VerticalPresetApplierTest extends TestCase
{
    public function testAppliesSettingsAndCreatesDefaultWarehouseWhenBranchExists(): void
    {
        $settings = new TenantSettings();
        $settings->setIgv(0.0)->setRequireCashRegister(false)->setAutoInvoice(false);

        $settingsRepo = $this->createMock(TenantSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->method('count')->willReturn(0);

        $branch = (new Branch())->setName('Principal');
        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->method('findOneBy')->willReturn($branch);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Warehouse::class));
        $em->expects(self::once())->method('flush');

        $applier = new VerticalPresetApplier($settingsRepo, $branchRepo, $warehouseRepo, $em);
        $result = $applier->apply(new SingleStoreRetailPreset());

        self::assertTrue($result->settingsApplied);
        self::assertTrue($result->defaultWarehouseCreated);
        self::assertSame([], $result->notes);

        // Settings defaults from the preset were applied onto the entity.
        self::assertSame(18.0, $settings->getIgv());
        self::assertTrue($settings->isRequireCashRegister());
        self::assertTrue($settings->isAutoInvoice());
        self::assertSame(DefaultCurrency::PEN, $settings->getDefaultCurrency());
        self::assertSame(StockPolicy::STRICT, $settings->getNegativeStockPolicy());
    }

    public function testSkipsWarehouseCreationWhenWarehouseAlreadyExists(): void
    {
        $settingsRepo = $this->createMock(TenantSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(new TenantSettings());

        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->method('count')->willReturn(2);

        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->expects(self::never())->method('findOneBy');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $applier = new VerticalPresetApplier($settingsRepo, $branchRepo, $warehouseRepo, $em);
        $result = $applier->apply(new SingleStoreRetailPreset());

        self::assertFalse($result->defaultWarehouseCreated);
    }

    public function testMultiLocationPresetDoesNotCreateDefaultWarehouse(): void
    {
        $settingsRepo = $this->createMock(TenantSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(new TenantSettings());

        // Even with no warehouses and an existing branch, a non-single-location
        // preset must not force a default warehouse.
        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->expects(self::never())->method('count');

        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->expects(self::never())->method('findOneBy');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $applier = new VerticalPresetApplier($settingsRepo, $branchRepo, $warehouseRepo, $em);
        $result = $applier->apply(new PharmacyPreset());

        self::assertTrue($result->settingsApplied);
        self::assertFalse($result->defaultWarehouseCreated);
        self::assertSame(PharmacyPreset::KEY, $result->presetKey);
    }

    public function testRecordsNoteWhenNoBranchExists(): void
    {
        $settingsRepo = $this->createMock(TenantSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(new TenantSettings());

        $warehouseRepo = $this->createMock(WarehouseRepository::class);
        $warehouseRepo->method('count')->willReturn(0);

        $branchRepo = $this->createMock(BranchRepository::class);
        $branchRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $applier = new VerticalPresetApplier($settingsRepo, $branchRepo, $warehouseRepo, $em);
        $result = $applier->apply(new SingleStoreRetailPreset());

        self::assertFalse($result->defaultWarehouseCreated);
        self::assertCount(1, $result->notes);
    }
}
