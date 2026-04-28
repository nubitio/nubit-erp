<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockAuthorizationPolicyInterface;
use App\Inventory\Core\Dto\StockAuthorizationResult;

/**
 * Default role-based authorization for inventory policy overrides.
 *
 * Who can override STRICT stock policy:
 *   - ROLE_INVENTORY_OVERRIDE  (explicit override grant, for future fine-grained use)
 *   - ROLE_SUPER_ADMIN         (full access)
 *   - ROLE_ADMIN               (operations access)
 *
 * Note: ROLE_INVENTORY_OVERRIDE is not yet emitted by User::getRoles() (the role
 * mapping uses integer role IDs 1-3). Until a dedicated role is added to the DB,
 * ROLE_ADMIN and ROLE_SUPER_ADMIN serve as the override path. See ADR-018 and the
 * User entity for role mapping context.
 */
final class RoleBasedStockAuthorization implements StockAuthorizationPolicyInterface
{
    /** @var list<string> */
    private const array OVERRIDE_ROLES = [
        'ROLE_INVENTORY_OVERRIDE',
        'ROLE_SUPER_ADMIN',
        'ROLE_ADMIN',
    ];

    /**
     * @param array<int, string> $roles
     */
    public function canOverrideStrictPolicy(
        Product $product,
        string $actorId,
        array $roles,
    ): StockAuthorizationResult {
        // Authorization is role-based only; $product is part of the interface
        // contract to allow future product-specific overrides (e.g. controlled
        // substances) but is intentionally unused in this implementation.
        unset($product);
        foreach (self::OVERRIDE_ROLES as $overrideRole) {
            if (in_array($overrideRole, $roles, true)) {
                return StockAuthorizationResult::granted();
            }
        }

        return StockAuthorizationResult::denied(
            sprintf(
                'Actor %s lacks any of [%s] to override strict stock policy',
                $actorId,
                implode(', ', self::OVERRIDE_ROLES),
            )
        );
    }
}
