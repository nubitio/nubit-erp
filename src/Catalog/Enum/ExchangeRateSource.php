<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

enum ExchangeRateSource: string
{
    case BCRP_AUTO = 'BCRP_AUTO';
    case MANUAL = 'MANUAL';
}
