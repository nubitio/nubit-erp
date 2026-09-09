<?php

declare(strict_types=1);

namespace App\Sales\Domain;

/**
 * Kinds of movement on a customer's current account (#367). Each row of
 * `customer_account_entries` is one of these; the sign convention is carried by
 * the entry's debit/credit columns, not by the type.
 *
 *  - CHARGE          a credit sale increases what the customer owes (debit).
 *  - PAYMENT         money received and applied to outstanding sales (credit).
 *  - ADVANCE         money received with no target sale — a customer advance
 *                    (credit to the account, liability elsewhere).
 *  - ADVANCE_APPLIED a previously received advance consumed by a sale; nets the
 *                    advance down without new money changing hands (credit).
 *  - CREDIT_NOTE     a credit note reduces what the customer owes (credit).
 */
enum CustomerAccountEntryType: string
{
    case CHARGE = 'charge';
    case PAYMENT = 'payment';
    case ADVANCE = 'advance';
    case ADVANCE_APPLIED = 'advance_applied';
    case CREDIT_NOTE = 'credit_note';
}
