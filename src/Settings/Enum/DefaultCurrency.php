<?php

declare(strict_types=1);

namespace App\Settings\Enum;

enum DefaultCurrency: string
{
    case PEN = 'PEN';
    case USD = 'USD';

    public static function default(): self
    {
        return self::PEN;
    }
}
