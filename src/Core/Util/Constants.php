<?php

declare(strict_types=1);

namespace App\Core\Util;

abstract class Constants
{
    // Storage paths
    public const string INVOICE_DIR = 'invoices';
    public const string IMAGE_DIR = 'images';
    public const string BACKUP_DIR = 'backups';
    public const string BINLOG_DIR = 'binlogs';

    // Settings
    public const string TAX = 'app_option_igv';

    public const string CONSUMPTION_TAX = 'app_option_rc';

    public const string AUTO_INVOICE = 'app_option_auto_invoice';

    public const string CHECK_STOCK = 'app_option_check_stock';

    public const string CPE_CLIENT_ID = 'cpe_client_id';

    public const string CPE_CLIENT_SECRET = 'cpe_client_secret';

    public const string PRINT_LOGO = 'app_option_print_logo';

    public const string REQUIRE_CASH_REGISTER = 'app_option_require_cash_register';

    public const string RECEIPT_REQUIRED = 'app_option_receipt_required';

    public const string SUNAT_SEND_ENABLED = 'app_option_sunat_send_enabled';

    public const string SUNAT_REJECTION_STREAK = 'app_option_sunat_rejection_streak';

    public const string SUNAT_REJECTION_THRESHOLD = 'app_option_sunat_rejection_threshold';

    public const string SUNAT_SEND_RETRY_MAX_ATTEMPTS = 'app_option_sunat_send_retry_max_attempts';

    public const string SUNAT_SEND_RETRY_BASE_SECONDS = 'app_option_sunat_send_retry_base_seconds';

    public const string SUNAT_SEND_RETRY_MAX_SECONDS = 'app_option_sunat_send_retry_max_seconds';

    public const string SALES_V2_DUAL_WRITE_ENABLED = 'app_option_sales_v2_dual_write_enabled';

    public const string SALES_V2_DUAL_WRITE_STRICT = 'app_option_sales_v2_dual_write_strict';

    public const string SALES_V2_MODE = 'sales_v2_mode';

    public const string SALES_V2_CUTOVER_DATE = 'sales_v2_cutover_date';

    public const string SALES_DEFAULT_CURRENCY = 'sales_default_currency';

    public const string SALES_DEFAULT_USD_EXCHANGE_RATE = 'sales_default_usd_exchange_rate';
}
