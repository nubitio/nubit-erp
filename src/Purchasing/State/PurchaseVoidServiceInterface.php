<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use App\Purchasing\Entity\Purchase;

interface PurchaseVoidServiceInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function reverse(Purchase $purchase): void;
}
