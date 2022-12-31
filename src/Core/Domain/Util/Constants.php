<?php

namespace App\Core\Domain\Util;

abstract class Constants
{
    public const CREATED_TEXT = 'Registro creado';
    public const UPDATED_TEXT = 'Registro actualizado';
    public const DELETED_TEXT = 'Registro eliminado';

    public const IMAGE_UPLOAD_DIR = 'uploads/images';

    // Settings
    public const TAX = 'app_option_igv';
    public const CONSUMPTION_TAX = 'app_option_rc';
}
