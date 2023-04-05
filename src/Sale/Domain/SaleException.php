<?php

namespace App\Sale\Domain;

use App\Core\Domain\Exception\ServiceException;

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
}