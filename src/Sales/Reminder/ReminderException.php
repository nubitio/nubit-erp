<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** A reminder request that cannot proceed, with the HTTP status the API should answer. */
final class ReminderException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $status,
        public readonly ?DateTimeImmutable $lastReminderAt = null,
    ) {
        parent::__construct($message);
    }

    public static function saleNotFound(): self
    {
        return new self('No se encontró la venta en esta sucursal.', 404);
    }

    public static function nothingToCollect(): self
    {
        return new self('La venta no tiene saldo pendiente.', 409);
    }

    public static function tooFrequent(DateTimeImmutable $lastReminderAt): self
    {
        return new self(
            sprintf(
                'Ya se envió un recordatorio por este canal el %s.',
                $lastReminderAt->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i'),
            ),
            409,
            $lastReminderAt,
        );
    }

    public static function missingRecipient(ReminderChannel $channel): self
    {
        return new self(
            $channel === ReminderChannel::Whatsapp
                ? 'El cliente no tiene un teléfono válido registrado.'
                : 'El cliente no tiene un correo registrado.',
            422,
        );
    }

    public static function invalid(string $message): self
    {
        return new self($message, 422);
    }
}
