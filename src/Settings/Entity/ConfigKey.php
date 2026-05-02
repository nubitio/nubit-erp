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
    case EXCHANGE_RATE_UPDATED_AT   = 'sales_usd_exchange_rate_updated_at';

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

    // ── Inventory-core migration (technical — hidden from normal UI) ──────────
    case INVENTORY_CORE_MODE                    = 'inventory_core_mode';
    case INVENTORY_CORE_DUAL_WRITE              = 'inventory_core_dual_write_enabled';
    case INVENTORY_CORE_READ_FROM_STOCK_ITEMS   = 'inventory_core_read_stock_items_enabled';

    // ── SUNAT send/retry internals (technical — managed by backend) ───────────
    case RECEIPT_REQUIRED                       = 'app_option_receipt_required';
    case SUNAT_SEND_ENABLED                     = 'app_option_sunat_send_enabled';
    case SUNAT_REJECTION_STREAK                 = 'app_option_sunat_rejection_streak';
    case SUNAT_REJECTION_THRESHOLD              = 'app_option_sunat_rejection_threshold';
    case SUNAT_SEND_RETRY_MAX_ATTEMPTS          = 'app_option_sunat_send_retry_max_attempts';
    case SUNAT_SEND_RETRY_BASE_SECONDS          = 'app_option_sunat_send_retry_base_seconds';
    case SUNAT_SEND_RETRY_MAX_SECONDS           = 'app_option_sunat_send_retry_max_seconds';

    // ── CPE credentials (technical — set during onboarding, not user-facing) ──
    case CPE_CLIENT_ID                          = 'cpe_client_id';
    case CPE_CLIENT_SECRET                      = 'cpe_client_secret';

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
            self::PURCHASES_V2_DUAL_WRITE_STRICT,
            self::INVENTORY_CORE_MODE,
            self::INVENTORY_CORE_DUAL_WRITE,
            self::INVENTORY_CORE_READ_FROM_STOCK_ITEMS,
            self::EXCHANGE_RATE_UPDATED_AT,
            self::RECEIPT_REQUIRED,
            self::SUNAT_SEND_ENABLED,
            self::SUNAT_REJECTION_STREAK,
            self::SUNAT_REJECTION_THRESHOLD,
            self::SUNAT_SEND_RETRY_MAX_ATTEMPTS,
            self::SUNAT_SEND_RETRY_BASE_SECONDS,
            self::SUNAT_SEND_RETRY_MAX_SECONDS,
            self::CPE_CLIENT_ID,
            self::CPE_CLIENT_SECRET => true,
            default => false,
        };
    }

    public function isFeatureFlag(): bool
    {
        return false; // No feature flags — print method is now a proper enum setting
    }
}
