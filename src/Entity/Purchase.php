<?php

namespace App\Entity;

use App\Purchasing\Entity\Purchase as PurchasingPurchase;

class_alias(PurchasingPurchase::class, Purchase::class);

/** @deprecated Use App\Purchasing\Entity\Purchase */
class Purchase extends PurchasingPurchase
{
}
