<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;

/**
 * Canonical tenant settings a vertical preset wants to apply during onboarding.
 *
 * This is a value object: it carries no behavior and is HTTP/persistence-agnostic.
 * The applier maps these values onto the TenantSettings entity.
 */
final readonly class VerticalPresetSettings
{
    public function __construct(
        public float $igv,
        public bool $requireCashRegister,
        public bool $autoInvoice,
        public DefaultCurrency $defaultCurrency,
        public StockPolicy $negativeStockPolicy,
        public PrintMethod $printMethod,
        public bool $autoPrint = true,
    ) {}
}
