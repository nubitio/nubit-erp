<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Capability\CapabilityCatalog;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantSettingsRepository;

use function in_array;

/**
 * Read-only counterpart of {@see VerticalPresetApplier}: computes what applying a
 * preset would change for the current tenant (NB-8), so a dry-run of
 * `tenant:apply-preset` can show a diff and a re-apply can be confirmed as a
 * no-op. It mirrors the applier's rules — manual/support capability overrides
 * are reported as skipped, not changed.
 */
final readonly class VerticalPresetPlanner
{
    private const array MANUAL_SOURCES = [CapabilitySource::Manual, CapabilitySource::Support];

    public function __construct(
        private TenantSettingsRepository $settingsRepository,
        private TenantCapabilityRepository $capabilityRepository,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
    ) {}

    public function plan(VerticalPresetInterface $preset): VerticalPresetPlan
    {
        [$createsWarehouse, $notes] = $this->planWarehouse($preset);

        return new VerticalPresetPlan(
            presetKey: $preset->key(),
            presetVersion: $preset->version(),
            settingsChanges: $this->planSettings($preset),
            capabilityChanges: $this->planCapabilities($preset),
            createsDefaultWarehouse: $createsWarehouse,
            notes: $notes,
        );
    }

    /**
     * @return list<array{field: string, from: string, to: string}>
     */
    private function planSettings(VerticalPresetInterface $preset): array
    {
        $current = $this->settingsRepository->getOrCreate();
        $target = $preset->settings();

        $pairs = [
            ['vertical', $current->getVertical(), $preset->key()],
            ['igv', (string) $current->getIgv(), (string) $target->igv],
            [
                'requireCashRegister',
                self::yn($current->isRequireCashRegister()),
                self::yn($target->requireCashRegister),
            ],
            ['autoInvoice', self::yn($current->isAutoInvoice()), self::yn($target->autoInvoice)],
            ['autoPrint', self::yn($current->isAutoPrint()), self::yn($target->autoPrint)],
            ['defaultCurrency', $current->getDefaultCurrency()->value, $target->defaultCurrency->value],
            ['negativeStockPolicy', $current->getNegativeStockPolicy()->value, $target->negativeStockPolicy->value],
            ['printMethod', $current->getPrintMethod()->value, $target->printMethod->value],
        ];

        $changes = [];
        foreach ($pairs as [$field, $from, $to]) {
            if ($from === $to) {
                continue;
            }

            $changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @return list<array{key: string, change: string}>
     */
    private function planCapabilities(VerticalPresetInterface $preset): array
    {
        $changes = [];
        foreach ($preset->desiredCapabilities() as $key) {
            $changes[] = ['key' => $key, 'change' => $this->classifyCapability($key)];
        }

        return $changes;
    }

    private function classifyCapability(string $key): string
    {
        $definition = CapabilityCatalog::get($key);
        if ($definition === null) {
            return VerticalPresetPlan::CAP_UNKNOWN;
        }

        $row = $this->capabilityRepository->findByKey($key);
        if ($row === null) {
            return VerticalPresetPlan::CAP_ENABLE;
        }
        if (in_array($row->getSource(), self::MANUAL_SOURCES, strict: true)) {
            return VerticalPresetPlan::CAP_SKIP_MANUAL;
        }
        if (!$row->isEnabled()) {
            return VerticalPresetPlan::CAP_ENABLE;
        }
        if ($row->getConfig() === null && $definition->safeDefaults !== []) {
            return VerticalPresetPlan::CAP_CONFIG;
        }

        return VerticalPresetPlan::CAP_NOOP;
    }

    /**
     * @return array{0: bool, 1: list<string>}
     */
    private function planWarehouse(VerticalPresetInterface $preset): array
    {
        if (!$preset->usesSingleStockLocation() || $this->warehouseRepository->count([]) > 0) {
            return [false, []];
        }

        if ($this->branchRepository->findOneBy([], ['name' => 'ASC']) === null) {
            return [false, ['No existe una sucursal; se omitiría la creación del almacén por defecto.']];
        }

        return [true, []];
    }

    private static function yn(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
