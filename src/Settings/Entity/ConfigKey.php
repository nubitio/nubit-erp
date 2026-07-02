<?php

declare(strict_types=1);

namespace App\Settings\Entity;

/**
 * @deprecated All config keys have been migrated to typed entities.
 *
 * This enum is kept only to serve the deprecated GET /api/configs endpoint
 * during the backward-compatibility transition window (4 weeks from deploy date).
 * After the transition, this file and the Config entity will be deleted.
 *
 * Do NOT add new keys here. Use TenantSettings, SunatOperationalState,
 * or ExchangeRate entities instead.
 */
enum ConfigKey: string
{
    // ── User-facing settings (now in TenantSettings entity) ──────────────────
    case IGV = 'app_option_igv';
    case RC = 'app_option_rc';
    case PRINT_LOGO = 'app_option_print_logo';
    case REQUIRE_CASH_REGISTER = 'app_option_require_cash_register';
    case AUTO_INVOICE = 'app_option_auto_invoice';
    case DEFAULT_CURRENCY = 'sales_default_currency';
    case INVENTORY_NEGATIVE_STOCK_POLICY = 'inventory_negative_stock_policy';
    case PRINT_METHOD = 'print_method';

    // ── SUNAT state (now in SunatOperationalState entity) ─────────────────────
    case RECEIPT_REQUIRED = 'app_option_receipt_required';
    case SUNAT_SEND_ENABLED = 'app_option_sunat_send_enabled';
    case SUNAT_REJECTION_STREAK = 'app_option_sunat_rejection_streak';
    case SUNAT_REJECTION_THRESHOLD = 'app_option_sunat_rejection_threshold';

    // ── CPE credentials (scope TBD) ───────────────────────────────────────────
    case CPE_CLIENT_ID = 'cpe_client_id';
    case CPE_CLIENT_SECRET = 'cpe_client_secret';

    /** All remaining keys are technical/internal — hidden from the deprecated UI. */
    public function isTechnical(): bool
    {
        return match ($this) {
            self::RECEIPT_REQUIRED,
            self::SUNAT_SEND_ENABLED,
            self::SUNAT_REJECTION_STREAK,
            self::SUNAT_REJECTION_THRESHOLD,
            self::CPE_CLIENT_ID,
            self::CPE_CLIENT_SECRET,
                => true,
            default => false,
        };
    }
}
