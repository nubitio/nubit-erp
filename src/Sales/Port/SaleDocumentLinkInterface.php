<?php

declare(strict_types=1);

namespace App\Sales\Port;

use App\Sales\Entity\SaleRecord;

/**
 * Public link to a sale's electronic document, so Sales can quote it in messages without
 * knowing how Billing builds receipt URLs.
 */
interface SaleDocumentLinkInterface
{
    /** Absolute public URL of the sale's voucher, or null when it has none. */
    public function publicUrl(SaleRecord $sale): ?string;
}
