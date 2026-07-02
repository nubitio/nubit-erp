<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

/**
 * Broad product kind — replaces the legacy tipo_id and simplifies product_type.
 *
 * GOOD    — physical goods (can be stockable or composite)
 * SERVICE — labour / service lines (never tracked)
 * DIGITAL — downloadable / license products (never tracked)
 */
enum ProductKind: string
{
    case GOOD = 'GOOD';
    case SERVICE = 'SERVICE';
    case DIGITAL = 'DIGITAL';

    /**
     * Derive from legacy tipo_id (1=physical, 2=service) for backward compatibility.
     */
    public static function fromLegacyTypeId(int $typeId): self
    {
        return match ($typeId) {
            1 => self::GOOD,
            default => self::SERVICE,
        };
    }

    /**
     * Derive from legacy ProductType string for backward compatibility.
     */
    public static function fromProductType(string $productType): self
    {
        return match (strtoupper($productType)) {
            'STOCKABLE', 'COMPOSITE', 'CONSUMABLE' => self::GOOD,
            'DIGITAL' => self::DIGITAL,
            default => self::SERVICE,
        };
    }
}
