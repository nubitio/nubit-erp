<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

/** Valida un documento de identidad (Tabla 2) de una parte de la guía. */
final class DespatchDocumentRule
{
    /** @return ?string Mensaje de error en español, o null si el documento es válido */
    public static function error(string $type, string $number, string $who): ?string
    {
        return match ($type) {
            '6' => preg_match('/^\d{11}$/', $number) === 1 ? null : sprintf('El RUC %s debe tener 11 dígitos.', $who),
            '1' => preg_match('/^\d{8}$/', $number) === 1 ? null : sprintf('El DNI %s debe tener 8 dígitos.', $who),
            '4', '7', '0' => trim($number) === '' ? sprintf('Debe indicar el documento %s.', $who) : null,
            default => sprintf('El tipo de documento %s no es válido.', $who),
        };
    }
}
