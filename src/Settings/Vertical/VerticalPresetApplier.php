<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use App\Settings\Contract\TenantWarehousePortInterface;
use App\Settings\Capability\CapabilityCatalog;
use App\Settings\Capability\SettingsCapabilitySync;
use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantProfileRepository;
use App\Settings\Repository\TenantSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

use function in_array;

/**
 * Applies a vertical preset onto the current tenant.
 *
 * Scope is deliberately conservative: it configures the TenantSettings
 * singleton, records the tenant profile, enables the preset's desired
 * capabilities, and ensures a Default Stock Location for single-location
 * presets. It never deletes existing data, so it is safe to re-run. Manual and
 * support capability overrides are left untouched.
 */
final readonly class VerticalPresetApplier
{
    private const string DEFAULT_WAREHOUSE_NAME = 'Almacén principal';

    public function __construct(
        private TenantSettingsRepository $settingsRepository,
        private TenantWarehousePortInterface $warehousePort,
        private TenantProfileRepository $profileRepository,
        private TenantCapabilityRepository $capabilityRepository,
        private SettingsCapabilitySync $settingsCapabilitySync,
        private EntityManagerInterface $entityManager,
    ) {}

    public function apply(VerticalPresetInterface $preset): VerticalPresetApplicationResult
    {
        $notes = [];

        $settings = $this->applySettings($preset);
        $this->profileRepository->getOrCreate()->recordApplied($preset->key(), $preset->version());
        $capabilitiesApplied = $this->applyCapabilities($preset);
        $this->settingsCapabilitySync->sync($settings);

        $defaultWarehouseCreated = false;
        if ($preset->usesSingleStockLocation()) {
            [$defaultWarehouseCreated, $note] = $this->ensureDefaultWarehouse();
            if ($note !== null) {
                $notes[] = $note;
            }
        }

        $this->entityManager->flush();

        return new VerticalPresetApplicationResult(
            presetKey: $preset->key(),
            presetVersion: $preset->version(),
            settingsApplied: true,
            defaultWarehouseCreated: $defaultWarehouseCreated,
            capabilitiesApplied: $capabilitiesApplied,
            notes: $notes,
        );
    }

    /**
     * Enables each capability the preset asks for, skipping unknown keys and
     * leaving manual/support overrides in place.
     */
    private function applyCapabilities(VerticalPresetInterface $preset): int
    {
        $manualSources = [CapabilitySource::Manual, CapabilitySource::Support];
        $applied = 0;

        foreach ($preset->desiredCapabilities() as $key) {
            $definition = CapabilityCatalog::get($key);
            if ($definition === null) {
                continue;
            }

            $capability = $this->capabilityRepository->findByKey($key);
            if ($capability !== null && in_array($capability->getSource(), $manualSources, strict: true)) {
                continue;
            }

            if ($capability === null) {
                $capability = new TenantCapability($key);
                $this->entityManager->persist($capability);
            }

            $capability
                ->setEnabled(true)
                ->setSource(CapabilitySource::Preset)
                ->setPreset($preset->key(), $preset->version());
            if ($capability->getConfig() === null && $definition->safeDefaults !== []) {
                $capability->setConfig($definition->safeDefaults);
            }
            $applied++;
        }

        return $applied;
    }

    private function applySettings(VerticalPresetInterface $preset): TenantSettings
    {
        $settings = $this->settingsRepository->getOrCreate();
        $values = $preset->settings();

        $settings
            ->setVertical($preset->key())
            ->setIgv($values->igv)
            ->setRequireCashRegister($values->requireCashRegister)
            ->setAutoInvoice($values->autoInvoice)
            ->setAutoPrint($values->autoPrint)
            ->setDefaultCurrency($values->defaultCurrency)
            ->setNegativeStockPolicy($values->negativeStockPolicy)
            ->setPrintMethod($values->printMethod);

        return $settings;
    }

    /**
     * Ensures a single default warehouse exists for a single-location tenant.
     *
     * @return array{0: bool, 1: ?string} [warehouseCreated, note]
     */
    private function ensureDefaultWarehouse(): array
    {
        if ($this->warehousePort->count() > 0) {
            return [false, null];
        }

        if (!$this->warehousePort->hasBranch()) {
            return [false, 'No existe una sucursal; se omitió la creación del almacén por defecto.'];
        }

        $this->warehousePort->createDefault(self::DEFAULT_WAREHOUSE_NAME);

        return [true, null];
    }
}
