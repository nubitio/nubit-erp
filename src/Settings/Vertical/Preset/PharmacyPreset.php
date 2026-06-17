<?php

declare(strict_types=1);

namespace App\Settings\Vertical\Preset;

use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Vertical\VerticalPresetInterface;
use App\Settings\Vertical\VerticalPresetSettings;

/**
 * Pharmacy vertical: lot/expiry tracking (FEFO) matters, negative stock is
 * never allowed for medicines. Topology (single vs multi store/warehouse) is
 * NOT encoded here — it is derived at runtime from the tenant's data and
 * entitlements, so one preset serves single- and multi-location pharmacies.
 */
final class PharmacyPreset implements VerticalPresetInterface
{
    public const string KEY = 'pharmacy';

    public function key(): string
    {
        return self::KEY;
    }

    public function version(): int
    {
        return 1;
    }

    public function label(): string
    {
        return 'Botica / Farmacia';
    }

    public function settings(): VerticalPresetSettings
    {
        return new VerticalPresetSettings(
            igv: 18.0,
            requireCashRegister: true,
            autoInvoice: true,
            defaultCurrency: DefaultCurrency::PEN,
            negativeStockPolicy: StockPolicy::STRICT,
            printMethod: PrintMethod::WEBUSB,
        );
    }

    public function desiredCapabilities(): array
    {
        return [
            'sales.core',
            'billing.sunat',
            'cash.sessions',
            'inventory.stock_tracking',
            'inventory.lot_tracking',
        ];
    }

    public function usesSingleStockLocation(): bool
    {
        return false;
    }
}
