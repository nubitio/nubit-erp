<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Dto\StockValidationResult;

interface StockValidationPolicyInterface
{
    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult;
}
