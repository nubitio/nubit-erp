<?php

declare(strict_types=1);

namespace App\Settings\Entity;

/**
 * Canonical registry of all known Config keys.
 *
 * Every key that has behavioural impact in the codebase MUST be listed here.
 * A Config row in the DB whose name is not present in this enum is ignored by
 * the application — it will never silently drive behaviour through a typo.
 */
enum ConfigKey: string
{
    // ── General ──────────────────────────────────────────────────────────────
    case IGV                        = 'app_option_igv';
    case RC                         = 'app_option_rc';
    case PRINT_LOGO                 = 'app_option_print_logo';
    case REQUIRE_CASH_REGISTER      = 'app_option_require_cash_register';

    // ── Sales ─────────────────────────────────────────────────────────────────
    case AUTO_INVOICE               = 'app_option_auto_invoice';
    case DEFAULT_CURRENCY           = 'sales_default_currency';
    case DEFAULT_USD_EXCHANGE_RATE  = 'sales_default_usd_exchange_rate';

    // ── Sales v2 migration (technical — hidden from normal UI) ────────────────
    case SALES_V2_MODE                          = 'sales_v2_mode';
    case SALES_V2_DUAL_WRITE_ENABLED            = 'app_option_sales_v2_dual_write_enabled';
    case SALES_V2_DUAL_WRITE_STRICT             = 'app_option_sales_v2_dual_write_strict';
    case SALES_V2_CUTOVER_DATE                  = 'sales_v2_cutover_date';
    case SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT  = 'sales_v2_disable_legacy_cash_movement';
    case SALES_V2_DISABLE_LEGACY_STOCK_PROJ     = 'sales_v2_disable_legacy_stock_projection';

    // ── Purchases v2 migration (technical — hidden from normal UI) ────────────
    case PURCHASES_V2_MODE                      = 'purchases_v2_mode';
    case PURCHASES_V2_DUAL_WRITE_ENABLED        = 'app_option_purchases_v2_dual_write_enabled';
    case PURCHASES_V2_DUAL_WRITE_STRICT         = 'app_option_purchases_v2_dual_write_strict';

    // ── Inventory ─────────────────────────────────────────────────────────────
    case INVENTORY_NEGATIVE_STOCK_POLICY        = 'inventory_negative_stock_policy';

    // ── Impresión ─────────────────────────────────────────────────────────────
    case PRINT_METHOD               = 'print_method';

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isTechnical(): bool
    {
        return match ($this) {
            self::SALES_V2_MODE,
            self::SALES_V2_DUAL_WRITE_ENABLED,
            self::SALES_V2_DUAL_WRITE_STRICT,
            self::SALES_V2_CUTOVER_DATE,
            self::SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT,
            self::SALES_V2_DISABLE_LEGACY_STOCK_PROJ,
            self::PURCHASES_V2_MODE,
            self::PURCHASES_V2_DUAL_WRITE_ENABLED,
            self::PURCHASES_V2_DUAL_WRITE_STRICT => true,
            default => false,
        };
    }

    public function isFeatureFlag(): bool
    {
        return false; // No feature flags — print method is now a proper enum setting
    }
}
