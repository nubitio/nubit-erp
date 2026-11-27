<?php

declare(strict_types=1);

namespace App\Sales\Domain;

/**
 * Kinds of movement on a customer's current account (#367). Each row of
 * `customer_account_entries` is one of these; the direction is carried by the
 * entry's debit/credit columns, not by the type.
 *
 *  - CHARGE       a credit sale increases what the customer owes (debit).
 *  - PAYMENT      money received and applied to outstanding sales (credit).
 *  - ADVANCE      money received with no target sale — a customer advance
 *                 (credit; the running balance goes into the customer's favour).
 *                 Applying an advance to a sale later does *not* add another
 *                 entry — it only records a `customer_payment_applications` row
 *                 against this one and lowers the sale's balance_due, so the
 *                 net running balance is unchanged (#382).
 *  - CREDIT_NOTE  a credit note reduces what the customer owes (credit).
 *  - CHARGE_REVERSAL cancels the still-open CHARGE of a voided credit sale
 *                 that had no collections applied to it (credit; see
 *                 SaleException::hasAppliedPayments(), which blocks voiding
 *                 once a collection exists).
 *  - PAYMENT_REVERSAL a registered collection is undone: what it settled is owed
 *                 again (debit). The original PAYMENT stays; nothing is deleted.
 */
enum CustomerAccountEntryType: string
{
    case CHARGE = 'charge';
    case PAYMENT = 'payment';
    case ADVANCE = 'advance';
    case CREDIT_NOTE = 'credit_note';
    case CHARGE_REVERSAL = 'charge_reversal';
    case PAYMENT_REVERSAL = 'payment_reversal';
}
