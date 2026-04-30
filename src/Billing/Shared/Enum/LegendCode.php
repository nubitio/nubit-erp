<?php

declare(strict_types=1);

namespace App\Billing\Shared\Enum;

enum LegendCode: string
{
    case TOTAL_AMOUNT_IN_WORDS = '1000'; // Monto total en letras
    case DETRACTION_OPERATION = '2006'; // Operación sujeta a detracción
    // Add other legend codes as needed
}
