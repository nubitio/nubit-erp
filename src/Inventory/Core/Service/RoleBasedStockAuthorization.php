<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Entity\Product;
use App\Inventory\Core\Contract\StockAuthorizationPolicyInterface;
use App\Inventory\Core\Dto\StockAuthorizationResult;

/**
 * Default role-based authorization.
 * Users with ROLE_INVENTORY_OVERRIDE can override STRICT policy.
 */
final class RoleBasedStockAuthorization implements StockAuthorizationPolicyInterface
{
    private const OVERRIDE_ROLE = 'ROLE_INVENTORY_OVERRIDE';

    public function canOverrideStrictPolicy(
        Product $product,
        string $actorId,
        array $roles,
    ): StockAuthorizationResult {
        if (in_array(self::OVERRIDE_ROLE, $roles, true)) {
            return StockAuthorizationResult::granted();
        }

        return StockAuthorizationResult::denied(
            sprintf('Actor %s lacks %s to override strict stock policy', $actorId, self::OVERRIDE_ROLE)
        );
    }
}
