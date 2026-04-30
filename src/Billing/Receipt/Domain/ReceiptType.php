<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

enum ReceiptType: string
{
    case FACTURA = '01';
    case BOLETA = '03';
    case NOTA_CREDITO = '07';
    case NOTA_DEBITO = '08';
}
