<?php

namespace App\Core\Domain\Util;

abstract class Constants
{
    public const IMAGE_UPLOAD_DIR = 'images/';
    // Settings
    public const COMPANY_ID = 'invoice_ruc';
    public const COMPANY_NAME = 'invoice_razon_social';
    public const TAX = 'app_option_igv';
    public const CONSUMPTION_TAX = 'app_option_rc';
    public const CPE_CLIENT_ID = 'cpe_client_id';
    public const CPE_CLIENT_SECRET = 'cpe_client_secret';
    public const EMAIL_ALERT_RECIPIENTS = 'app_alert_recipients';
    public const PHONE_ALERT_RECIPIENTS = 'app_wsp_recipients';
}
