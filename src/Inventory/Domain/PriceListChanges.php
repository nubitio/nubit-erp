<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Dto\PriceItemDto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * What a price-list save actually changed, in the shape of the audit trail.
 *
 * Rows are written with SQL, so there is no entity for the audit listener to diff;
 * this does the same job for them: one entry per product whose special price was
 * added or changed, keyed by the product's name, with the price it had (null when
 * it had none and followed the base price) and the one it has now.
 */
final class PriceListChanges
{
    /**
     * @param array<string, string> $before productId => stored price
     * @param array<PriceItemDto> $items the submitted rows
     * @param array<string, string> $names productId => product name
     *
     * @return array<string, array{before: ?string, after: string}>
     */
    public static function diff(array $before, array $items, array $names): array
    {
        $changes = [];

        foreach ($items as $item) {
            $productId = $item->productId;
            if ($productId === null || $item->price === null) {
                continue;
            }

            $after = BigDecimal::of($item->price)->toScale(4, RoundingMode::HALF_UP);
            $previous = $before[$productId] ?? null;

            if ($previous !== null && BigDecimal::of($previous)->compareTo($after) === 0) {
                continue;
            }

            $changes[$names[$productId] ?? $productId] = [
                'before' => $previous === null
                    ? null
                    : (string) BigDecimal::of($previous)->toScale(4, RoundingMode::HALF_UP),
                'after' => (string) $after,
            ];
        }

        return $changes;
    }
}
