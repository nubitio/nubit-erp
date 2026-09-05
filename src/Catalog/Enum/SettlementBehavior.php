<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Describes how a POS option contributes to settling a sale.
 *
 * COLLECTED is money received through a real payment channel. RECEIVABLE is a
 * convenience catalogue option used by the POS to allocate an amount to the
 * customer's outstanding balance; it must never create a SalePayment row.
 */
enum SettlementBehavior: string
{
    case COLLECTED = 'COLLECTED';
    case RECEIVABLE = 'RECEIVABLE';
}
