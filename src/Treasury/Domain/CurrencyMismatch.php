<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** A payment is never converted: it must be in the currency of the account it touches (decision 3). */
final class CurrencyMismatch extends TreasuryException
{
    public static function between(string $account, string $payment): self
    {
        return new self(sprintf(
            'La cuenta es en %s y el pago es en %s; no se convierte automáticamente.',
            $account,
            $payment,
        ));
    }
}
