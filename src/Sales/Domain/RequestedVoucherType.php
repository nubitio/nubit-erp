<?php

declare(strict_types=1);

namespace App\Sales\Domain;

/**
 * Voucher type explicitly requested by the cashier for a sale, as opposed to
 * DocType which holds the actual SUNAT document type codes. AUTO preserves
 * the historical behaviour of deciding Factura/Boleta from the customer's
 * RUC length.
 */
enum RequestedVoucherType: string
{
    case AUTO = 'AUTO';
    case FACTURA = 'FACTURA';
    case BOLETA = 'BOLETA';
}
