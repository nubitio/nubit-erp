<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use DateTimeImmutable;
use Mike42\Escpos\Printer;

/** The text of a till ticket: lines that fit the paper, readable by a printer without accents. */
readonly class TicketText
{
    private const array ASCII = [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n',
        'ü' => 'u',
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U',
        'Ñ' => 'N',
        '°' => '.',
        '−' => '-',
        '·' => '-',
        '–' => '-',
    ];

    public function title(Printer $printer, string $text): void
    {
        $this->strong($printer, $text . "\n");
    }

    public function strong(Printer $printer, string $line): void
    {
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($line);
        $printer->selectPrintMode();
    }

    /** A label on the left and a value on the right, within the width of the paper. */
    public function row(string $label, string $value, int $width): string
    {
        $label = $this->ascii($label);
        $value = $this->ascii($value);
        $room = $width - strlen($value) - 1;
        if (strlen($label) > $room) {
            $label = substr($label, 0, max(0, $room));
        }

        return str_pad($label, $width - strlen($value)) . $value . "\n";
    }

    public function wrap(string $text, int $width): string
    {
        return wordwrap($this->ascii(trim($text)), $width, "\n", true) . "\n";
    }

    /** The printer's default code page has no accents: it prints what it can read. */
    public function ascii(string $text): string
    {
        return strtr($text, self::ASCII);
    }

    public function num(string $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    public function date(?string $iso): string
    {
        return $iso === null ? '-' : new DateTimeImmutable($iso)->format('d/m/Y H:i');
    }
}
