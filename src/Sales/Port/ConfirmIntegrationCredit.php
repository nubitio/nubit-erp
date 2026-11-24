<?php

declare(strict_types=1);

namespace App\Sales\Port;

interface ConfirmIntegrationCredit
{
    /**
     * The adapter has authorized the tenant and branch and opened the intake transaction.
     *
     * @param list<array{product: string, qty: string, unitPrice: string}> $items
     */
    public function confirmIntegrationCredit(
        string $clientId,
        string $branchId,
        string $warehouseId,
        int $customerId,
        array $items,
        string $expectedTotal,
    ): string;
}
