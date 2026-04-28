<?php

declare(strict_types=1);

namespace App\Purchasing\State;

final class PurchasesWriteMode
{
    public const string LEGACY = 'legacy';
    public const string DUAL_WRITE = 'dual_write';
    public const string AUTHORITATIVE = 'v2_authoritative';

    public static function normalize(string $raw): string
    {
        $value = strtolower(trim($raw));

        if (in_array($value, [self::LEGACY, self::DUAL_WRITE, self::AUTHORITATIVE], true)) {
            return $value;
        }

        return self::DUAL_WRITE;
    }
}
