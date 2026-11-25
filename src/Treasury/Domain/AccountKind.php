<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** What kind of place the money sits in. Suspense catches movements with no configured account. */
enum AccountKind: string
{
    case CashRegister = 'cash_register';
    case Bank = 'bank';
    case Wallet = 'wallet';
    case Clearing = 'clearing';
    case Suspense = 'suspense';

    public static function configurable(mixed $value): ?self
    {
        return match ($value ?? 'wallet') {
            'wallet' => self::Wallet,
            'clearing' => self::Clearing,
            default => null,
        };
    }
}
