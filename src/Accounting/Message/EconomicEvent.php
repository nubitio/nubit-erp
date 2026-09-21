<?php

declare(strict_types=1);

namespace App\Accounting\Message;

/**
 * A business fact that Accounting turns into a balanced journal entry.
 * Dispatched on the sync messenger transport so the asiento is in the same request.
 */
final readonly class EconomicEvent
{
    public const string SOURCE_SALE = 'sale';

    public const string SOURCE_PURCHASE = 'purchase';

    public const string SOURCE_CASH = 'cash_movement';

    public const string SOURCE_RECEIVABLE_COLLECT = 'receivable_collect';

    public const string SOURCE_CUSTOMER_ADVANCE = 'customer_advance';

    public const string SOURCE_OPENING_RECEIVABLE = 'opening_receivable';

    public const string SOURCE_PAYABLE_PAYMENT = 'payable_payment';

    public const string SOURCE_PAYABLE_PAYMENT_REVERSAL = 'payable_payment_reversal';

    public const string SOURCE_SUPPLIER_BILL = 'supplier_bill';

    public const string SOURCE_SUPPLIER_BILL_REVERSAL = 'supplier_bill_reversal';

    public const string SALE_CASH = 'SALE_CASH';

    public const string SALE_CREDIT = 'SALE_CREDIT';

    public const string PURCHASE = 'PURCHASE';

    public const string CASH_IN = 'CASH_IN';

    public const string CASH_OUT = 'CASH_OUT';

    /** Collection of a receivable paid in cash: debit Caja, credit Facturas por cobrar. */
    public const string RECEIVABLE_COLLECT_CASH = 'RECEIVABLE_COLLECT_CASH';

    /** Collection of a receivable paid by transfer/POS/deposit: debit Banco, credit Facturas por cobrar. */
    public const string RECEIVABLE_COLLECT_BANK = 'RECEIVABLE_COLLECT_BANK';

    /** Customer advance received in cash: debit Caja, credit Anticipos de clientes (122). */
    public const string CUSTOMER_ADVANCE_RECEIVED_CASH = 'CUSTOMER_ADVANCE_RECEIVED_CASH';

    /** Customer advance received by transfer/POS/deposit: debit Banco, credit Anticipos de clientes (122). */
    public const string CUSTOMER_ADVANCE_RECEIVED_BANK = 'CUSTOMER_ADVANCE_RECEIVED_BANK';

    /** A held customer advance applied to a sale: debit Anticipos de clientes (122), credit Facturas por cobrar. */
    public const string CUSTOMER_ADVANCE_APPLIED = 'CUSTOMER_ADVANCE_APPLIED';

    /** Opening customer debt imported from another system: debit receivables, credit opening equity. */
    public const string OPENING_RECEIVABLE = 'OPENING_RECEIVABLE';

    /** Payment of a supplier bill: debit payables, credit cash. */
    public const string PAYABLE_PAYMENT_CASH = 'PAYABLE_PAYMENT_CASH';

    /** Payment of a supplier bill: debit payables, credit bank. */
    public const string PAYABLE_PAYMENT_BANK = 'PAYABLE_PAYMENT_BANK';

    /** Reversal of a cash supplier-bill payment. */
    public const string PAYABLE_PAYMENT_REVERSE_CASH = 'PAYABLE_PAYMENT_REVERSE_CASH';

    /** Reversal of a bank supplier-bill payment. */
    public const string PAYABLE_PAYMENT_REVERSE_BANK = 'PAYABLE_PAYMENT_REVERSE_BANK';

    /** Direct supplier expense bill: debit expense, credit payables. */
    public const string SUPPLIER_BILL = 'SUPPLIER_BILL';

    /** Void of a direct supplier expense bill: debit payables, credit expense. */
    public const string SUPPLIER_BILL_REVERSE = 'SUPPLIER_BILL_REVERSE';

    public function __construct(
        public string $source,
        public string $sourceId,
        public string $kind,
        public string $occurredOn,
        public string $description,
        public string $total,
        public string $tax = '0.00',
        public string $cogs = '0.00',
        public string $currency = 'PEN',
    ) {}
}
