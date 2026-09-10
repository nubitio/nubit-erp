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
