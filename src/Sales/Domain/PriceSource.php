<?php

declare(strict_types=1);

namespace App\Sales\Domain;

/** Where the unit price of a sale line came from, decided by the server when the sale is written. */
enum PriceSource: string
{
    /** The product's own sale price. */
    case CATALOG = 'catalog';

    /** The customer's price list: a special price or the list's factor over the base price. */
    case PRICE_LIST = 'price_list';

    /** Typed by someone with permission to change prices, or quoted online at a price that is no longer current. */
    case MANUAL = 'manual';

    /**
     * An offline sale, already delivered to the customer, whose price differs from the one in
     * force when it reached the server (usually a till that had not refreshed its catalogue).
     * Accepted, because rejecting it would not undo the sale; listed for someone to review.
     */
    case OFFLINE_DIFF = 'offline_diff';
}
