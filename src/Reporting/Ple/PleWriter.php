<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use DateTimeInterface;

/**
 * Utilidades de formato para los Libros Electrónicos (PLE) de SUNAT.
 *
 * Reglas del formato de texto del PLE:
 *  - Los campos se separan con el carácter pipe «|» y cada campo —incluido el
 *    último— termina con un pipe.
 *  - Los registros se separan con salto de línea CRLF.
 *  - Los importes usan punto como separador decimal, dos decimales y sin
 *    separador de miles.
 *  - Las fechas se expresan como DD/MM/AAAA.
 *  - Ningún valor de campo puede contener el separador «|» ni saltos de línea.
 */
final class PleWriter
{
    /**
     * Sanea el valor de un campo: elimina separadores y normaliza espacios.
     */
    public static function field(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = is_float($value) ? self::amount($value) : (string) $value;

        return trim(str_replace(['|', "\r", "\n"], ' ', $text));
    }

    /**
     * Formatea un importe con dos decimales y punto decimal.
     */
    public static function amount(string|int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * Formatea una fecha como DD/MM/AAAA. Devuelve cadena vacía si es null.
     */
    public static function date(?DateTimeInterface $date): string
    {
        return $date?->format('d/m/Y') ?? '';
    }

    /**
     * Ensambla un registro: cada campo seguido de «|» y el registro cerrado con CRLF.
     *
     * @param list<string|int|float|null> $fields
     */
    public static function record(array $fields): string
    {
        $line = '';
        foreach ($fields as $field) {
            $line .= self::field($field) . '|';
        }

        return $line . "\r\n";
    }

    /**
     * Construye el nombre de archivo del PLE según la nomenclatura de SUNAT:
     *   LE + RUC + AAAA + MM + 00 + CodLibro + 00 + IndOper + IndContenido + IndMoneda + IndLibro + .txt
     *
     * @param string $ruc      RUC del emisor (11 dígitos)
     * @param string $period   Periodo en formato AAAAMM
     * @param string $bookCode Código de libro (p. ej. «140100» ventas, «080100» compras)
     * @param bool   $hasData  Si el libro tiene movimientos (indicador de contenido)
     */
    public static function fileName(string $ruc, string $period, string $bookCode, bool $hasData): string
    {
        $year = substr($period, 0, 4);
        $month = substr($period, 4, 2);
        $contentIndicator = $hasData ? '1' : '0';

        return sprintf(
            'LE%s%s%s00%s00%s%s11.txt',
            $ruc,
            $year,
            $month,
            $bookCode,
            // Indicadores SUNAT: operaciones, contenido, moneda (1 = PEN), libro generado.
            '1',
            $contentIndicator,
        );
    }
}
