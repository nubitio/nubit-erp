<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Sales\Entity\SaleRecord;

/**
 * Contract for resolving the canonical cash session associated with a sale.
 */
interface CashSessionResolverInterface
{
    /**
     * Resolves the cash session ID for the given sale.
     *
     * @throws ServiceException when no session can be determined
     */
    public function resolveForSale(SaleRecord $sale): int;

    /**
     * Like resolveForSale() but returns null instead of throwing when no session is found.
     *
     * @throws ServiceException when the branch cannot be determined (infrastructure error)
     */
    public function tryResolveForSale(SaleRecord $sale): ?int;

    /**
     * Returns the cash_register_id for an already-resolved session, or null if not set.
     */
    public function resolveRegisterIdForSession(int $sessionId): ?int;
}
