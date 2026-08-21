<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\ProductType;
use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Service\TenantSettingsService;

/**
 * Resolves the per-tenant stock validation policy from the generic config store
 * (inventory_negative_stock_policy key) and applies it to sale validation.
 *
 * The policy is stored as a StockPolicy enum string value (STRICT / WARNING_ONLY /
 * ALLOW_NEGATIVE) — the same values used by NegativeStockPolicyResolver for the
 * legacy sales pipeline, so a single config key drives both code paths.
 */
final readonly class TenantStockValidationPolicy implements StockValidationPolicyInterface
{
    public function __construct(
        private TenantSettingsService $tenantSettings,
    ) {}

    public function validateSale(
        Product $product,
        string $warehouseId,
        string $requestedQtyBase,
        string $onHandQtyBase,
    ): StockValidationResult {
        if (!$this->tracksInventory($product)) {
            return StockValidationResult::allowed();
        }

        $remaining = (float) $onHandQtyBase - (float) $requestedQtyBase;
        if ($remaining >= -0.0000005) {
            return StockValidationResult::allowed();
        }

        $policy = $this->resolvePolicy();

        return match ($policy) {
            StockPolicy::STRICT => StockValidationResult::blocked('Stock insuficiente para completar la venta'),
            StockPolicy::WARNING_ONLY => StockValidationResult::warning('El stock quedaría en negativo'),
            StockPolicy::ALLOW_NEGATIVE => StockValidationResult::allowed(),
        };
    }

    private function resolvePolicy(): StockPolicy
    {
        return $this->tenantSettings->getNegativeStockPolicy();
    }

    private function tracksInventory(Product $product): bool
    {
        $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior());
        if ($behavior === InventoryBehavior::NOT_TRACKED) {
            return false;
        }

        $productType = ProductType::tryFrom($product->getProductType());
        if ($productType !== null && !$productType->tracksInventory()) {
            return false;
        }

        $productKind = ProductKind::tryFrom($product->getProductKind());
        if (in_array($productKind, [ProductKind::SERVICE, ProductKind::DIGITAL], true)) {
            return false;
        }

        if ($product->getType() !== 1) {
            return false;
        }

        return $product->getTrackInventory();
    }
}
