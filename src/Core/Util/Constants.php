<?php

declare(strict_types=1);

namespace App\Core\Util;

abstract class Constants
{
    public const string IMAGE_UPLOAD_DIR = 'images/';

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
}
