<?php

declare(strict_types=1);

namespace App\Sale\Domain;

enum DocType: string
{
    case BOLETA = '03';
    case FACTURA = '01';
    case NOTA_VENTA = '00';
}
