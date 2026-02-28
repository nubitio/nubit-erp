<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\StockPolicy;
use Doctrine\DBAL\Connection;

final readonly class TenantStockValidationPolicy implements StockValidationPolicyInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult {
        $productType = $this->resolveProductType($product);
        if (!in_array($productType, ['STOCKABLE', 'CONSUMABLE', 'COMPOSITE'], true)) {
            return StockValidationResult::allowed();
        }

        $remaining = ((float) $onHandQtyBase) - ((float) $requestedQtyBase);
        if ($remaining >= -0.0000005) {
            return StockValidationResult::allowed();
        }

        $policy = $this->resolvePolicy();

        return match ($policy) {
            StockPolicy::STRICT => StockValidationResult::blocked('Insufficient stock for STRICT policy'),
            StockPolicy::WARNING_ONLY => StockValidationResult::warning('Stock would go negative under WARNING_ONLY'),
            StockPolicy::ALLOW_NEGATIVE => StockValidationResult::allowed(),
        };
    }

    private function resolvePolicy(): StockPolicy
    {
        $raw = $this->connection->fetchOne(
            'SELECT policy FROM tenant_inventory_policy ORDER BY id DESC LIMIT 1'
        );

        if ($raw === false) {
            return StockPolicy::STRICT;
        }

        return StockPolicy::from((string) $raw);
    }

    private function resolveProductType(Product $product): string
    {
        if (method_exists($product, 'getProductType')) {
            return strtoupper((string) ($product->getProductType() ?? 'STOCKABLE'));
        }

        return $product->getType() === 1 ? 'STOCKABLE' : 'SERVICE';
    }
}
