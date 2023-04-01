<?php

namespace App\Core\Domain\Util;

abstract class Constants
{
    public const CREATED_TEXT = 'Registro creado';
    public const UPDATED_TEXT = 'Registro actualizado';
    public const DELETED_TEXT = 'Registro eliminado';

    public const IMAGE_UPLOAD_DIR = 'images/';

    // Settings
    public const COMPANY_ID = 'invoice_ruc';
    public const COMPANY_NAME = 'invoice_razon_social';
    public const TAX = 'app_option_igv';
    public const CONSUMPTION_TAX = 'app_option_rc';
}
