<?php

declare(strict_types=1);

namespace App\Identity\Activity;

/** Makes a value safe to open in a spreadsheet. */
final class CsvCell
{
    /** A cell starting with one of these is executed as a formula by Excel / Sheets / LibreOffice. */
    private const string FORMULA_PREFIXES = "=+-@\t\r";

    public static function safe(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return $text !== '' && str_contains(self::FORMULA_PREFIXES, $text[0]) ? "'" . $text : $text;
    }
}
