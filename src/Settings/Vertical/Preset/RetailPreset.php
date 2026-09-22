<?php

declare(strict_types=1);

namespace App\Settings\Vertical\Preset;

use App\Efact\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Vertical\VerticalPresetInterface;
use App\Settings\Vertical\VerticalPresetSettings;

/**
 * General retail vertical: the default starting point for a new tenant.
 *
 * The name encodes the vertical only. Topology (single vs multi store/warehouse)
 * is derived at runtime from the tenant's data and entitlements;
 * `usesSingleStockLocation` merely expresses the default intent for a fresh
 * tenant (Tienda Unica), which runtime data can later override.
 */
final class RetailPreset implements VerticalPresetInterface
{
    public const string KEY = 'retail';

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
        return 'Retail / Tienda';
    }

    public function settings(): VerticalPresetSettings
    {
        return new VerticalPresetSettings(
            igv: 18.0,
            requireCashRegister: true,
            autoInvoice: true,
            autoPrint: true,
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
        ];
    }

    public function usesSingleStockLocation(): bool
    {
        return true;
    }
}
