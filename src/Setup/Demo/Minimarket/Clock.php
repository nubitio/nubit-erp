<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

/** The shop lives on Lima time but the database stores UTC, exactly like the real till does. */
final class Clock
{
    public static function utc(DateTimeImmutable $at): DateTime
    {
        return DateTime::createFromImmutable($at)->setTimezone(new DateTimeZone('UTC'));
    }

    public static function utcImmutable(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone('UTC'));
    }

    public static function stamp(DateTimeImmutable $at): string
    {
        return self::utcImmutable($at)->format('Y-m-d H:i:s');
    }

    public static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
