<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Repository\TenantSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies a vertical preset onto the current tenant.
 *
 * Scope is deliberately conservative: it configures the TenantSettings singleton
 * and ensures a Default Stock Location for single-location presets. It never
 * deletes existing branches/warehouses, so it is safe to re-run.
 */
final readonly class VerticalPresetApplier
{
    private const string DEFAULT_WAREHOUSE_NAME = 'Almacén principal';

    public function __construct(
        private TenantSettingsRepository $settingsRepository,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function apply(VerticalPresetInterface $preset): VerticalPresetApplicationResult
    {
        $notes = [];

        $this->applySettings($preset);

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
            notes: $notes,
        );
    }

    private function applySettings(VerticalPresetInterface $preset): void
    {
        $settings = $this->settingsRepository->getOrCreate();
        $values = $preset->settings();

        $settings
            ->setVertical($preset->key())
            ->setIgv($values->igv)
            ->setRequireCashRegister($values->requireCashRegister)
            ->setAutoInvoice($values->autoInvoice)
            ->setDefaultCurrency($values->defaultCurrency)
            ->setNegativeStockPolicy($values->negativeStockPolicy)
            ->setPrintMethod($values->printMethod);
    }

    /**
     * Ensures a single default warehouse exists for a single-location tenant.
     *
     * @return array{0: bool, 1: ?string} [warehouseCreated, note]
     */
    private function ensureDefaultWarehouse(): array
    {
        if ($this->warehouseRepository->count([]) > 0) {
            return [false, null];
        }

        $defaultBranch = $this->branchRepository->findOneBy([], ['name' => 'ASC']);
        if ($defaultBranch === null) {
            return [false, 'No existe una sucursal; se omitió la creación del almacén por defecto.'];
        }

        $warehouse = new Warehouse()
            ->setName(self::DEFAULT_WAREHOUSE_NAME)
            ->setBranch($defaultBranch);

        $this->entityManager->persist($warehouse);

        return [true, null];
    }
}
