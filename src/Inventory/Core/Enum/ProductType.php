<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

enum ProductType: string
{
    case STOCKABLE = 'STOCKABLE';
    case SERVICE = 'SERVICE';
    case DIGITAL = 'DIGITAL';
    case COMPOSITE = 'COMPOSITE';
    case CONSUMABLE = 'CONSUMABLE';

    public function tracksInventory(): bool
    {
        return match ($this) {
            self::STOCKABLE, self::CONSUMABLE, self::COMPOSITE => true,
            self::SERVICE, self::DIGITAL => false,
        };
    }
}
