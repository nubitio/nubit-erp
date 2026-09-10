<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Describes how a POS option contributes to settling a sale.
 *
 * COLLECTED is money received through a real payment channel. RECEIVABLE is a
 * convenience catalogue option used by the POS to allocate an amount to the
 * customer's outstanding balance; it must never create a SalePayment row.
 * ADVANCE is an internal method: a customer advance being applied to a sale
 * (#382). It creates a SalePayment row but no cash movement — the money moved
 * when the advance was received — and is not offered in the POS.
 */
enum SettlementBehavior: string
{
    case COLLECTED = 'COLLECTED';
    case RECEIVABLE = 'RECEIVABLE';
    case ADVANCE = 'ADVANCE';
}
