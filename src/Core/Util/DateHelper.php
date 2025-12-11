<?php

declare(strict_types=1);

namespace App\Core\Util;

use DateTimeImmutable;
use DateTimeInterface;
use DateTime;

final class DateHelper
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable()->setTime(0, 0);
    }

    public static function tomorrow(): DateTimeImmutable
    {
        return self::today()->modify('+1 day');
    }

    public static function yesterday(): DateTimeImmutable
    {
        return self::today()->modify('-1 day');
    }

    public static function parse(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    public static function format(DateTimeInterface $date, string $format = 'Y-m-d H:i:s'): string
    {
        return $date->format($format);
    }

    public static function diffInDays(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) $from->diff($to)->days;
    }

    public static function diffInHours(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $diff = $from->diff($to);
        return ($diff->days * 24) + $diff->h;
    }

    public static function diffInMinutes(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $diff = $from->diff($to);
        return (($diff->days * 24 + $diff->h) * 60) + $diff->i;
    }

    public static function addDays(DateTimeInterface $date, int $days): DateTimeImmutable
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        }

        return $date->modify(sprintf('%+d days', $days));
    }

    public static function addHours(DateTimeInterface $date, int $hours): DateTimeImmutable
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        }

        return $date->modify(sprintf('%+d hours', $hours));
    }

    public static function addMinutes(DateTimeInterface $date, int $minutes): DateTimeImmutable
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        }

        return $date->modify(sprintf('%+d minutes', $minutes));
    }

    public static function isWeekend(DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('N'), [6, 7], true);
    }

    public static function isWeekday(DateTimeInterface $date): bool
    {
        return !self::isWeekend($date);
    }

    public static function startOfMonth(DateTimeInterface $date): DateTimeImmutable
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        }

        return $date->modify('first day of this month')->setTime(0, 0);
    }

    public static function endOfMonth(DateTimeInterface $date): DateTimeImmutable
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        }

        return $date->modify('last day of this month')->setTime(23, 59, 59);
    }
}
