<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Sales\Entity\SaleRecord;

interface StockVoidReverserInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function reverse(SaleRecord $sale): void;
}
