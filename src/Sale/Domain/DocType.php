<?php

namespace App\Sale\Domain;

enum DocType: string
{
    case BOLETA = '03';
    case FACTURA = '01';
    case NOTA_VENTA = '00';
}