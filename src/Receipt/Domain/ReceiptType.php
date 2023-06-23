<?php

namespace App\Receipt\Domain;

enum ReceiptType: string
{
    case FACTURA = '01';
    case BOLETA = '03';
    case NOTA_CREDITO = '07';
    case NOTA_DEBITO = '08';
}
