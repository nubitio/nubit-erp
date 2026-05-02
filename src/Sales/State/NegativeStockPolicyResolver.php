<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Identity\Dto\AuthUserDto;
use App\Inventory\Core\Enum\StockPolicy;
use App\Catalog\Entity\Product;

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
        private SettingsService $settings,
    ) {
    }

    public function resolvePolicy(): StockPolicy
    {
        $raw = strtoupper(trim((string) $this->settings->get(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')));

        return match ($raw) {
            'STRICT', 'BLOCK'                  => StockPolicy::STRICT,
            'WARNING_ONLY', 'ALLOW_WITH_ROLE'  => StockPolicy::WARNING_ONLY,
            'ALLOW_NEGATIVE', 'ALLOW_ALL'      => StockPolicy::ALLOW_NEGATIVE,
            default                            => StockPolicy::STRICT,
        };
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
        $mode = strtolower(trim((string) $this->settings->get(Constants::INVENTORY_CORE_MODE, 'legacy')));
        if ($mode === 'v2_authoritative') {
            return true;
        }

        return filter_var(
            $this->settings->get(Constants::INVENTORY_CORE_DUAL_WRITE, 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function isStockTracked(Product $product): bool
    {
        return $product->getTrackInventory();
    }

    private function isAdmin(AuthUserDto $user): bool
    {
        $roles = $user->roles ?? [];

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
