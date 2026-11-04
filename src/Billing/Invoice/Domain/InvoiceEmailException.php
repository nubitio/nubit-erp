<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use RuntimeException;
use Throwable;

/** A receipt e-mail that cannot be sent for a reason the cashier can act on. */
final class InvoiceEmailException extends RuntimeException
{
    public static function missingRecipient(): self
    {
        return new self('El cliente no tiene correo. Indica un correo para enviar el comprobante.');
    }

    public static function invalidRecipient(): self
    {
        return new self('El correo indicado no es válido.');
    }

    public static function xmlNotReady(string $document, Throwable $previous): self
    {
        return new self(
            "El comprobante {$document} aún no tiene XML: espera a que se envíe a SUNAT e inténtalo de nuevo.",
            previous: $previous,
        );
    }
}
