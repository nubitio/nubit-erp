<?php

declare(strict_types=1);

namespace App\Efact\Config;

/**
 * Efact-owned storage path constants.
 *
 * Config keys (app_option_*, sales_*, inventory_*, etc.) have been removed
 * from this class and are no longer stored as generic key/value pairs.
 * They now live in typed entities:
 *
 *  - TenantSettings entity   → user-facing settings (IGV, auto_invoice, etc.)
 *  - SunatOperationalState   → SUNAT send flag, receipt required, streak
 *  - ExchangeRate entity     → USD/PEN exchange rate
 *  - sunat.yaml parameters   → retry policy (max_attempts, base_seconds, max_seconds)
 *
 * Only storage path segments remain here.
 */
abstract class EfactConstants
{
    // ── Storage paths (still needed) ─────────────────────────────────────────
    public const string INVOICE_DIR = 'invoices';
    public const string IMAGE_DIR = 'images';
    public const string BACKUP_DIR = 'backups';
    public const string BINLOG_DIR = 'binlogs';
}
