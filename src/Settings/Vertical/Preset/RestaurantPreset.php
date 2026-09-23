<?php

declare(strict_types=1);

namespace App\Settings\Vertical\Preset;

use App\Efact\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Vertical\VerticalPresetInterface;
use App\Settings\Vertical\VerticalPresetSettings;

/**
 * Restaurant vertical (NB-18): table service on top of the same POS/Sales
 * core. A single implicit stock location is assumed for v1 — multi-branch
 * floor plans are out of scope until a real need surfaces.
 */
final class RestaurantPreset implements VerticalPresetInterface
{
    public const string KEY = 'restaurant';

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
        return 'Restaurante';
    }

    public function settings(): VerticalPresetSettings
    {
        return new VerticalPresetSettings(
            igv: 18.0,
            requireCashRegister: true,
            autoInvoice: true,
            autoPrint: true,
            defaultCurrency: DefaultCurrency::PEN,
            negativeStockPolicy: StockPolicy::WARNING_ONLY,
            printMethod: PrintMethod::WEBUSB,
        );
    }

    public function desiredCapabilities(): array
    {
        return [
            'sales.core',
            'billing.sunat',
            'cash.sessions',
            'restaurant.table_management',
        ];
    }

    public function usesSingleStockLocation(): bool
    {
        return true;
    }
}
