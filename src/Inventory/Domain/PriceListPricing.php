<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The one rule that turns a product and a price list into a price. The list editor,
 * the "apply" endpoint the till and the sale form read, and the server-side check
 * when a sale is written all go through here, so they cannot disagree.
 *
 *   1. a special price stored for the product in the list          (override)
 *   2. the product's base price × the list's factor, if it has one (multiplier)
 *   3. the product's base price                                    (base)
 */
final class PriceListPricing
{
    public const string OVERRIDE = 'override';
    public const string MULTIPLIER = 'multiplier';
    public const string BASE = 'base';

    /** @return array{price: string, source: 'override'|'multiplier'|'base'} price with four decimals */
    public static function resolve(string $basePrice, ?string $multiplier, ?string $override): array
    {
        if ($override !== null) {
            return ['price' => self::fourDecimals($override), 'source' => self::OVERRIDE];
        }

        if ($multiplier !== null && $multiplier !== '') {
            $price = BigDecimal::of($basePrice)->multipliedBy($multiplier);

            return ['price' => self::fourDecimals((string) $price), 'source' => self::MULTIPLIER];
        }

        return ['price' => self::fourDecimals($basePrice), 'source' => self::BASE];
    }

    private static function fourDecimals(string $value): string
    {
        return (string) BigDecimal::of($value)->toScale(4, RoundingMode::HALF_UP);
    }
}
