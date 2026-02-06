<?php

declare(strict_types=1);

namespace App\EInvoicing\Shared\Enum;

enum OperationType: string
{
    case SALE = '0101'; // Venta Interna
    case DETRACTION_SALE = '1001'; // Venta Sujeta a Detracción
    // Add other operation types as needed
}
