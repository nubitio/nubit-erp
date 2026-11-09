<?php

declare(strict_types=1);

namespace App\Identity\Invitation;

/** Spanish copy for the English messages the bundle's invitation service throws. */
final class InvitationMessages
{
    public static function translate(string $message): string
    {
        return match (true) {
            str_contains($message, 'valid email') => 'Escribe un correo válido.',
            str_contains($message, 'already has an account') => 'Ese correo ya tiene una cuenta.',
            default => $message,
        };
    }
}
