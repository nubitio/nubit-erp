<?php

declare(strict_types=1);

namespace App\Settings\Enum;

enum TaxRegime: string
{
    case GENERAL = 'GENERAL';
    case NRUS = 'NRUS';

    public static function default(): self
    {
        return self::GENERAL;
    }
}
