<?php

declare(strict_types=1);

namespace App\EInvoicing\Shared\Enum;

enum ChargeType: string
{
    case CONSUMPTION_SURCHARGE = '46'; // Tipo de cargo por recargo al consumo
    case DISCOUNT = '02'; // Tipo de cargo por descuento
    // Add other charge types as needed
}
