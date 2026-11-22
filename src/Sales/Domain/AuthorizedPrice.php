<?php

declare(strict_types=1);

namespace App\Sales\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Decides whether a submitted unit price is the one the catalogue or the customer's
 * price list dictates.
 *
 * The tariff is computed with up to four decimals (base × multiplier, or a stored
 * 4-decimal override) but the till shows and sends money with two. A price is
 * therefore authorised when it equals the expected price at four decimals or
 * rounded to two (half up); otherwise a legitimate list price would be reported as
 * a manual override and refused to cashiers without `pos.price_override`.
 */
final class AuthorizedPrice
{
    public static function matches(string $submitted, string $expected): bool
    {
        $submittedPrice = BigDecimal::of($submitted);
        $expectedPrice = BigDecimal::of($expected);

        foreach ([4, 2] as $scale) {
            if ($submittedPrice->compareTo($expectedPrice->toScale($scale, RoundingMode::HALF_UP)) === 0) {
                return true;
            }
        }

        return false;
    }
}
