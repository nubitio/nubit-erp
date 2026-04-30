<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

interface VoidStrategyInterface
{
    public function supports(string $documentType): bool;

    public function void(string $saleId, string $reason): SunatVoidResponse;
}
