<?php

declare(strict_types=1);

namespace App\Pharmacy\Enum;

/**
 * How strictly a pharmacy product's batch expiry is enforced at point of sale.
 * Consumed by the FEFO stock policy (NB-11); NONE is the safe default.
 */
enum ExpiryControl: string
{
    case None = 'NONE';
    case Warn = 'WARN';
    case Block = 'BLOCK';
}
