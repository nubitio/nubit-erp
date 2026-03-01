<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

/**
 * Inventory behavior for a product.
 *
 * TRACKED     — normal stockable product; movements are generated per unit
 * NOT_TRACKED — service/digital; zero stock movements ever
 * COMPOSITE   — bundle/recipe; movements generated for BOM components only,
 *               not for the parent product itself
 *
 * Invariants enforced by the domain:
 *   - NOT_TRACKED  → track_inventory = false
 *   - TRACKED      → track_inventory = true
 *   - COMPOSITE    → track_inventory = true (components are tracked)
 */
enum InventoryBehavior: string
{
    case TRACKED     = 'TRACKED';
    case NOT_TRACKED = 'NOT_TRACKED';
    case COMPOSITE   = 'COMPOSITE';

    public function tracksInventory(): bool
    {
        return $this !== self::NOT_TRACKED;
    }

    public function generatesOwnMovements(): bool
    {
        return $this === self::TRACKED;
    }

    public function requiresBomExplosion(): bool
    {
        return $this === self::COMPOSITE;
    }

    /**
     * Derive from legacy ProductType string for backward compatibility.
     */
    public static function fromProductType(string $productType): self
    {
        return match (strtoupper($productType)) {
            'COMPOSITE'            => self::COMPOSITE,
            'SERVICE', 'DIGITAL'   => self::NOT_TRACKED,
            default                => self::TRACKED,
        };
    }
}
