<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

/** Turns a stored customer phone into the digits-only international number wa.me expects. */
final class WhatsappNumber
{
    private const string PERU_COUNTRY_CODE = '51';

    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        // A bare Peruvian mobile number (9 digits starting with 9) gets the country code.
        if (strlen($digits) === 9 && $digits[0] === '9') {
            return self::PERU_COUNTRY_CODE . $digits;
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
