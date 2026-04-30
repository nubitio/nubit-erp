<?php

declare(strict_types=1);

namespace App\Core\Util;

use App\Billing\Receipt\ReceiptPayload as BillingReceiptPayload;

/**
 * @deprecated Use App\Billing\Receipt\ReceiptPayload. Receipt URLs are Billing/Receipt domain language, not Core utility code.
 */
final class ReceiptPayload
{
    private function __construct()
    {
    }

    public static function encode(string $type, string $id): string
    {
        return BillingReceiptPayload::encode($type, $id);
    }

    public static function publicUrl(string $type, string $id): string
    {
        return BillingReceiptPayload::publicUrl($type, $id);
    }
}
