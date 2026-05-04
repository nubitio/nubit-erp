<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Settings\Service\TenantSettingsService;
use App\Identity\Dto\AuthUserDto;
use App\Inventory\Core\Enum\StockPolicy;
use App\Catalog\Entity\Product;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\ProductType;

/**
 * Resolves the negative stock policy for the legacy sales pipeline.
 *
 * Reads the same `inventory_negative_stock_policy` config key as
 * TenantStockValidationPolicy so that both code paths are driven by a
 * single source of truth.  The policy value is stored as a StockPolicy
 * enum string (STRICT / WARNING_ONLY / ALLOW_NEGATIVE); legacy DB values
 * (BLOCK / ALLOW_WITH_ROLE / ALLOW_ALL) are accepted as synonyms.
 */
final readonly class NegativeStockPolicyResolver
{
    public function __construct(
        private TenantSettingsService $tenantSettings,
    ) {
    }

    public function resolvePolicy(): StockPolicy
    {
        return $this->tenantSettings->getNegativeStockPolicy();
    }

    /**
     * Returns true when the current user is allowed to complete a sale that
     * would result in negative stock, given the resolved policy.
     */
    public function canSell(StockPolicy $policy, AuthUserDto $user): bool
    {
        return match ($policy) {
            StockPolicy::ALLOW_NEGATIVE => true,
            StockPolicy::WARNING_ONLY   => $this->isAdmin($user),
            StockPolicy::STRICT         => false,
        };
    }

    public function shouldUseCoreValidation(): bool
    {
        return true; // Inventory Core V2 is now the only mode — migration flags removed.
    }

    public function isStockTracked(Product $product): bool
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

    private function isAdmin(AuthUserDto $user): bool
    {
        $roles = $user->roles ?? [];

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
