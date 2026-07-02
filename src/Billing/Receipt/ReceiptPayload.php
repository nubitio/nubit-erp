<?php

declare(strict_types=1);

namespace App\Billing\Receipt;

final class ReceiptPayload
{
    private function __construct() {}

    public static function encode(string $type, string $id): string
    {
        return base64_encode(json_encode([
            'type' => $type,
            'id' => $id,
        ], JSON_UNESCAPED_SLASHES));
    }

    public static function publicUrl(string $type, string $id): string
    {
        return '/receipt/' . self::encode($type, $id);
    }
}
