<?php

namespace App\Util;

class DateUtils
{
    public static function getDateWithTimeZone(\DateTimeInterface $date, string $timeZone = "America/Lima"): \DateTimeInterface
    {
        $timezone = new \DateTimeZone($timeZone);
        if ($date instanceof \DateTime) {
            $date = clone $date;
            return $date->setTimezone($timezone);
        }

        return $date->setTimezone($timezone);
    }
}