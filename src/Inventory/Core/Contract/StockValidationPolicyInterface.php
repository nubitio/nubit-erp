<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Catalog\Entity\Product;
use App\Efact\Extension\AsExtensionPoint;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Service\TenantStockValidationPolicy;

#[AsExtensionPoint(
    id: 'inventory.stock_validation_policy',
    summary: 'Whether and how a sale line may draw stock the warehouse does not have.',
    default: TenantStockValidationPolicy::class,
    resolution: AsExtensionPoint::RESOLUTION_BINDING,
)]
interface StockValidationPolicyInterface
{
    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult;
}
