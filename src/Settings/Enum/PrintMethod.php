<?php

declare(strict_types=1);

namespace App\Settings\Enum;

enum PrintMethod: string
{
    case WEBUSB      = 'WEBUSB';
    case LOCAL_AGENT = 'LOCAL_AGENT';

    public static function default(): self
    {
        return self::WEBUSB;
    }
}
