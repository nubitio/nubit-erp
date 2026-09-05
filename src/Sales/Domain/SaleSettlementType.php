<?php

declare(strict_types=1);

namespace App\Sales\Domain;

enum SaleSettlementType: string
{
    case PAYMENT = 'PAYMENT';
    case RECEIVABLE = 'RECEIVABLE';
}
