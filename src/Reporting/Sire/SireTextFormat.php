<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Transliterator;

/**
 * Formato de los campos del TXT de SIRE (RVIE/RCE).
 *
 * Las reglas de formato (fechas DD/MM/AAAA, importes con dos decimales, número
 * sin ceros a la izquierda, texto en ASCII sin «|», «/» ni «\») están
 * portadas de `drivers/kit/texto.py` de contaperu (https://github.com/contaperu/contaperu,
 * © 2026 Global Procesos AI S.A.C., licencia MIT).
 */
final class SireTextFormat
{
    /** Separador de campo; ningún valor puede contenerlo. */
    public const string SEPARATOR = '|';

    /** Cada línea termina en CRLF. */
    public const string NEWLINE = "\r\n";

    /** Importe con dos decimales; el cero siempre «0.00» y el signo «-» solo si no es cero. */
    public static function money(BigDecimal|string|int|null $value, bool $negative = false): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $amount = ($value instanceof BigDecimal ? $value : BigDecimal::of((string) $value))->abs()->toScale(
            2,
            RoundingMode::HALF_UP,
        );

        if ($amount->isZero()) {
            return '0.00';
        }

        return ($negative ? '-' : '') . $amount;
    }

    /** Importe que ya trae su propio signo (p. ej. «base + descuento» con descuento negativo). */
    public static function signedMoney(BigDecimal $value): string
    {
        return self::money($value, $value->isNegative());
    }

    public static function date(?DateTimeInterface $date): string
    {
        return $date?->format('d/m/Y') ?? '';
    }

    /** Número del comprobante sin ceros a la izquierda («00028806» → «28806»). */
    public static function number(?string $number): string
    {
        $value = trim((string) $number);
        if ($value !== '' && ctype_digit($value)) {
            return ltrim($value, '0') ?: '0';
        }

        return $value;
    }

    /** Tipo de cambio con tres decimales; vacío en soles (SIRE lo exige solo si la moneda no es PEN). */
    public static function exchangeRate(string $currency, BigDecimal|string|null $rate): string
    {
        if ($currency === 'PEN' || $rate === null || $rate === '') {
            return '';
        }

        $value = $rate instanceof BigDecimal ? $rate : BigDecimal::of((string) $rate);

        return $value->isZero() ? '' : (string) $value->toScale(3, RoundingMode::HALF_UP);
    }

    /**
     * Texto apto para un campo: sin «|» ni controles, «/» y «\» → «-», espacios
     * colapsados y plegado a ASCII (Ñ → N, tildes fuera).
     */
    public static function text(?string $text): string
    {
        $clean = (string) preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $text);
        $clean = str_replace([self::SEPARATOR, '/', '\\'], [' ', '-', '-'], $clean);
        $clean = self::toAscii($clean);

        return trim((string) preg_replace('/\s+/', ' ', $clean));
    }

    private static function toAscii(string $text): string
    {
        static $transliterator = null;
        $transliterator ??= Transliterator::create('Any-Latin; Latin-ASCII') ?? false;

        $ascii = $transliterator !== false ? $transliterator->transliterate($text) : $text;

        // Lo que no tiene equivalente ASCII desaparece, como en contaperu.
        return (string) preg_replace('/[^\x20-\x7e]/', '', is_string($ascii) ? $ascii : $text);
    }

    /**
     * Nombre oficial del libro electrónico: `LE` + RUC + periodo + día + código del libro +
     * oportunidad + cuatro banderas + extensión.
     */
    public static function bookFileName(
        string $ruc,
        string $period,
        string $bookCode,
        string $opportunity,
        string $flags,
        string $extension,
    ): string {
        return sprintf('LE%s%s00%s%s%s%s', $ruc, $period, $bookCode, $opportunity, $flags, $extension);
    }
}
