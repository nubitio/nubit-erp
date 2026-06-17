<?php

declare(strict_types=1);

namespace App\Settings\Runtime;

use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Repository\TenantSettingsRepository;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;

final readonly class TenantRuntimeConfigProvider
{
    public function __construct(
        private FeatureCheckerInterface $featureChecker,
        private TenantSettingsRepository $settingsRepository,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $settings = $this->settingsRepository->getOrCreate();
        $entitlements = $this->featureChecker->getEntitlements();

        $branchCount = $this->branchRepository->count([]);
        $warehouseCount = $this->warehouseRepository->count([]);
        $defaultBranch = $this->branchRepository->findOneBy([], ['name' => 'ASC']);
        $defaultWarehouse = $this->warehouseRepository->findOneBy([], ['name' => 'ASC']);

        $multiWarehouse = $this->isFeatureEnabled($entitlements, 'multi_warehouse');
        $lotTracking = $this->isFeatureEnabled($entitlements, 'lot_tracking');
        $advancedReports = $this->isFeatureEnabled($entitlements, 'advanced_reports');
        $excelExport = $this->isFeatureEnabled($entitlements, 'excel_export');

        $simpleStoreMode = $branchCount <= 1 && !$multiWarehouse;

        return [
            'profile' => [
                'preset' => $simpleStoreMode ? 'single_store_retail' : 'custom',
                'simpleStoreMode' => $simpleStoreMode,
            ],
            'counts' => [
                'branches' => $branchCount,
                'warehouses' => $warehouseCount,
            ],
            'defaults' => [
                'branch' => $defaultBranch === null ? null : [
                    'id' => $defaultBranch->getId(),
                    'name' => $defaultBranch->getName(),
                ],
                'warehouse' => $defaultWarehouse === null ? null : [
                    'id' => $defaultWarehouse->getId(),
                    'name' => $defaultWarehouse->getName(),
                ],
            ],
            'capabilities' => [
                'sales.core' => $this->capability(true),
                'billing.sunat' => $this->capability(true),
                'cash.sessions' => $this->capability(true, [
                    'requireOpenSession' => $settings->isRequireCashRegister(),
                ]),
                'inventory.stock_tracking' => $this->capability(true, [
                    'negativeStockPolicy' => $settings->getNegativeStockPolicy()->value,
                ]),
                'inventory.multi_warehouse' => $this->capability($multiWarehouse),
                'inventory.lot_tracking' => $this->capability($lotTracking),
                'reporting.advanced' => $this->capability($advancedReports),
                'exports.excel' => $this->capability($excelExport),
            ],
            'settings' => [
                'requireCashRegister' => $settings->isRequireCashRegister(),
                'autoInvoice' => $settings->isAutoInvoice(),
                'printMethod' => $settings->getPrintMethod()->value,
                'defaultCurrency' => $settings->getDefaultCurrency()->value,
                'negativeStockPolicy' => $settings->getNegativeStockPolicy()->value,
            ],
            'ui' => [
                'shell' => [
                    'showBranchPicker' => $branchCount > 1,
                ],
                'inventory' => [
                    'showWarehouseSelector' => $multiWarehouse && $warehouseCount > 1,
                    'showWarehouseManagement' => $multiWarehouse || $warehouseCount > 1,
                    'showTransfers' => $multiWarehouse,
                    'showLotFields' => $lotTracking,
                ],
                'reports' => [
                    'showAdvancedReports' => $advancedReports,
                    'showExcelExport' => $excelExport,
                ],
            ],
        ];
    }

    /**
     * @param array<string, array{enabled: bool, config: array<string, mixed>}> $entitlements
     */
    private function isFeatureEnabled(array $entitlements, string $featureKey): bool
    {
        return $entitlements[$featureKey]['enabled'] ?? false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{enabled: bool, config: array<string, mixed>}
     */
    private function capability(bool $enabled, array $config = []): array
    {
        return [
            'enabled' => $enabled,
            'config' => $config,
        ];
    }
}
