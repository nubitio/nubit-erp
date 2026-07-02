<?php

declare(strict_types=1);

namespace App\Billing\Shared\Service;

use App\Efact\Domain\Money;
use Brick\Math\Exception\MathException;
use NumberFormatter;

final readonly class AmountFormatterService
{
    /**
     * Formats a monetary amount into a Peruvian Soles text representation (e.g., "SON CIEN CON 50 / 100 SOLES").
     *
     * @throws MathException
     */
    public function formatAmountInWords(float $amount, string $currency = 'SOLES'): string
    {
        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $amountString = Money::toString(Money::money($amount));
        $parts = explode('.', $amountString);
        $integerPart = (int) $parts[0];
        $decimalPart = $parts[1] ?? '00';

        $formattedInteger = $formatter->format($integerPart);

        return mb_strtoupper('SON ' . $formattedInteger . ' CON ' . $decimalPart . ' / 100 ' . $currency);
    }
}
