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

    public const string SALE_CASH = 'SALE_CASH';

    public const string SALE_CREDIT = 'SALE_CREDIT';

    public const string PURCHASE = 'PURCHASE';

    public const string CASH_IN = 'CASH_IN';

    public const string CASH_OUT = 'CASH_OUT';

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
