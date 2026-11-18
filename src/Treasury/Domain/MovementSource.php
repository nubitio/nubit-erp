<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** The business event a movement comes from; together with the id it is the idempotency key. */
enum MovementSource: string
{
    case SalePayment = 'sale_payment';
    case ReceivableCollection = 'receivable_collection';
    case CustomerAdvance = 'customer_advance';
    case SupplierPayment = 'supplier_payment';
    case PayrollPayment = 'payroll_payment';
    case Transfer = 'transfer';
    case OpeningBalance = 'opening_balance';
    case StatementAdjustment = 'statement_adjustment';
    case Reclassification = 'reclassification';
}
