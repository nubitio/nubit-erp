<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

use App\Organization\Entity\Branch;
use App\Settings\Contract\TenantWarehousePortInterface;
use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetPlan;
use App\Settings\Vertical\VerticalPresetPlanner;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class VerticalPresetPlannerTest extends TestCase
{
    public function testFreshTenantPlanEnablesEveryDesiredCapabilityAndChangesSettings(): void
    {
        $plan = $this->planner(new TenantSettings(), [])->plan(new PharmacyPreset());

        static::assertTrue($plan->hasChanges());
        static::assertContains('vertical', array_column($plan->settingsChanges, 'field'));
        static::assertSame(new PharmacyPreset()->desiredCapabilities(), $plan->enabledCapabilities());
    }

    public function testPlanIsANoOpWhenTheSettingsAndCapabilitiesAlreadyMatch(): void
    {
        $settings = new TenantSettings();
        $values = new RetailPreset()->settings();
        $settings
            ->setVertical(RetailPreset::KEY)
            ->setIgv($values->igv)
            ->setRequireCashRegister($values->requireCashRegister)
            ->setAutoInvoice($values->autoInvoice)
            ->setAutoPrint($values->autoPrint)
            ->setDefaultCurrency($values->defaultCurrency)
            ->setNegativeStockPolicy($values->negativeStockPolicy)
            ->setPrintMethod($values->printMethod);

        $rows = [];
        foreach (new RetailPreset()->desiredCapabilities() as $key) {
            $row = new TenantCapability($key);
            $row->setEnabled(true)->setSource(CapabilitySource::Preset)->setConfig([]);
            $rows[$key] = $row;
        }

        $plan = $this->planner($settings, $rows)->plan(new RetailPreset());

        static::assertFalse($plan->hasChanges());
        static::assertSame([], $plan->settingsChanges);
        static::assertSame([], $plan->enabledCapabilities());
    }

    public function testManualOverrideIsReportedAsSkippedNotChanged(): void
    {
        $manual = new TenantCapability('cash.sessions');
        $manual->setEnabled(false)->setSource(CapabilitySource::Support);

        $plan = $this->planner(new TenantSettings(), ['cash.sessions' => $manual])->plan(new RetailPreset());

        $byKey = [];
        foreach ($plan->capabilityChanges as $change) {
            $byKey[$change['key']] = $change['change'];
        }

        static::assertSame(VerticalPresetPlan::CAP_SKIP_MANUAL, $byKey['cash.sessions']);
        static::assertSame(VerticalPresetPlan::CAP_ENABLE, $byKey['sales.core']);
        static::assertNotContains('cash.sessions', $plan->enabledCapabilities());
    }

    public function testSingleLocationPresetPlansADefaultWarehouseWhenNoneExists(): void
    {
        $planner = $this->planner(
            new TenantSettings(),
            [],
            warehouseCount: 0,
            branch: new Branch()->setName('Principal'),
        );

        static::assertTrue($planner->plan(new RetailPreset())->createsDefaultWarehouse);
    }

    /**
     * @param array<string, TenantCapability> $rows
     */
    private function planner(
        TenantSettings $settings,
        array $rows,
        int $warehouseCount = 1,
        ?Branch $branch = null,
    ): VerticalPresetPlanner {
        /** @var TenantSettingsRepository&MockObject $settingsRepo */
        $settingsRepo = $this->createMock(TenantSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        /** @var TenantCapabilityRepository&MockObject $capabilityRepo */
        $capabilityRepo = $this->createMock(TenantCapabilityRepository::class);
        $capabilityRepo
            ->method('findByKey')
            ->willReturnCallback(static fn(string $key): ?TenantCapability => $rows[$key] ?? null);

        /** @var TenantWarehousePortInterface&MockObject $warehousePort */
        $warehousePort = $this->createMock(TenantWarehousePortInterface::class);
        $warehousePort->method('count')->willReturn($warehouseCount);
        $warehousePort->method('hasBranch')->willReturn($branch !== null);

        return new VerticalPresetPlanner($settingsRepo, $capabilityRepo, $warehousePort);
    }
}
