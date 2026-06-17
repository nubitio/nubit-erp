<?php

declare(strict_types=1);

namespace App\Settings\Vertical\Preset;

use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Vertical\VerticalPresetInterface;
use App\Settings\Vertical\VerticalPresetSettings;

/**
 * Default product experience: one physical store with one implicit stock
 * location (Tienda Unica). Advanced capabilities stay available but hidden.
 */
final class SingleStoreRetailPreset implements VerticalPresetInterface
{
    public const string KEY = 'single_store_retail';

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
        return 'Tienda única (retail)';
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
        ];
    }

    public function usesSingleStockLocation(): bool
    {
        return true;
    }
}
