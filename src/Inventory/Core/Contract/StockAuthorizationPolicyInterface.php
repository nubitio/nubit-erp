<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Entity\Product;
use App\Inventory\Core\Dto\StockAuthorizationResult;

/**
 * Role-based authorization: can this actor override the stock policy?
 *
 * Intentionally decoupled from StockValidationPolicyInterface.
 * Policy = business rule. Authorization = actor permission.
 */
interface StockAuthorizationPolicyInterface
{
    public function canOverrideStrictPolicy(
        Product $product,
        string $actorId,
        array $roles,
    ): StockAuthorizationResult;
}
