<?php

declare(strict_types=1);

namespace App\Sale\Domain;

use App\Core\Exception\ServiceException;

class SaleException extends ServiceException
{
    public static function alreadyVoided(): self
    {
        return new self('La venta ya ha sido anulada');
    }

    public static function tooOld(): self
    {
        return new self('No se puede anular una venta con más de una semana de antigüedad');
    }

    public static function notIssued(): self
    {
        return new self('No se puede anular una venta que no ha sido registrada en SUNAT');
    }

    public static function hasCreditNote(): self
    {
        return new self('No se puede anular una venta que ya tiene nota de crédito');
    }
}
