<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Core\Context\TenantContext;
use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\StockPolicy;
use Doctrine\DBAL\Connection;

final readonly class TenantStockValidationPolicy implements StockValidationPolicyInterface
{
    public function __construct(
        private Connection $connection,
        private ?TenantContext $tenantContext = null,
    ) {
    }

    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult {
        if (!$this->tracksInventory($product)) {
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
        $tenantId = (string) ($this->tenantContext?->getTenantId() ?? 'default');
        $raw = $this->connection->fetchOne(
            'SELECT policy FROM tenant_inventory_policy WHERE tenant_id = :tenant_id LIMIT 1',
            ['tenant_id' => $tenantId]
        );

        if ($raw === false) {
            if ($tenantId !== 'default') {
                $fallback = $this->connection->fetchOne(
                    'SELECT policy FROM tenant_inventory_policy WHERE tenant_id = :tenant_id LIMIT 1',
                    ['tenant_id' => 'default']
                );
                if ($fallback !== false) {
                    return StockPolicy::from((string) $fallback);
                }
            }

            return StockPolicy::STRICT;
        }

        return StockPolicy::from((string) $raw);
    }

    private function tracksInventory(Product $product): bool
    {
        return $product->getTrackInventory();
    }
}
