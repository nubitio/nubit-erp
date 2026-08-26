<?php

declare(strict_types=1);

namespace App\Settings\Runtime;

use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Vertical\VerticalPresetRegistry;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;

final readonly class TenantRuntimeConfigProvider
{
    public function __construct(
        private FeatureCheckerInterface $featureChecker,
        private TenantSettingsRepository $settingsRepository,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
        private VerticalPresetRegistry $presetRegistry,
    ) {}

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
        $navigationTier = $this->resolveNavigationTier(
            multiWarehouse: $multiWarehouse,
            branchCount: $branchCount,
            warehouseCount: $warehouseCount,
            advancedReports: $advancedReports,
            excelExport: $excelExport,
        );

        // The vertical is the persisted onboarding choice (source of truth),
        // validated against the known presets; topology stays a separate axis.
        $vertical = $settings->getVertical();
        if (!$this->presetRegistry->has($vertical)) {
            $vertical = 'custom';
        }

        return [
            'profile' => [
                'preset' => $vertical,
                'vertical' => $vertical,
                'simpleStoreMode' => $simpleStoreMode,
                'navigationTier' => $navigationTier,
                'onboardingCompleted' => $settings->isOnboardingCompleted(),
            ],
            'counts' => [
                'branches' => $branchCount,
                'warehouses' => $warehouseCount,
            ],
            'defaults' => [
                'branch' => $defaultBranch === null
                    ? null
                    : [
                        'id' => $defaultBranch->getId(),
                        'name' => $defaultBranch->getName(),
                    ],
                'warehouse' => $defaultWarehouse === null
                    ? null
                    : [
                        'id' => $defaultWarehouse->getId(),
                        'name' => $defaultWarehouse->getName(),
                    ],
            ],
            'capabilities' => [
                'sales.core' => $this->capability(true),
                'billing.sunat' => $this->capability(true),
                'billing.recurring' => $this->capability($this->isFeatureEnabled($entitlements, 'recurring_billing')),
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
                'payroll.core' => $this->capability($this->isFeatureEnabled($entitlements, 'payroll')),
                'treasury.bank_reconciliation' => $this->capability($this->isFeatureEnabled(
                    $entitlements,
                    'bank_reconciliation',
                )),
                'fixed_assets.tracking' => $this->capability($this->isFeatureEnabled($entitlements, 'fixed_assets')),
                'expense_reports.core' => $this->capability($this->isFeatureEnabled($entitlements, 'expense_reports')),
                'accounting.general_ledger' => $this->capability($this->isFeatureEnabled(
                    $entitlements,
                    'general_ledger',
                )),
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
                'pos' => [
                    'allowLinePriceOverride' => $vertical !== 'pharmacy',
                    'allowCatalogPriceEdit' => $vertical !== 'pharmacy',
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

    private function resolveNavigationTier(
        bool $multiWarehouse,
        int $branchCount,
        int $warehouseCount,
        bool $advancedReports,
        bool $excelExport,
    ): string {
        if ($advancedReports || $excelExport) {
            return 'advanced';
        }

        if ($multiWarehouse || $branchCount > 1 || $warehouseCount > 1) {
            return 'standard';
        }

        return 'basic';
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
