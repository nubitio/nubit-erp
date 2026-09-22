<?php

declare(strict_types=1);

namespace App\Efact\Enum;

enum StockPolicy: string
{
    case STRICT = 'STRICT';
    case WARNING_ONLY = 'WARNING_ONLY';
    case ALLOW_NEGATIVE = 'ALLOW_NEGATIVE';
}
