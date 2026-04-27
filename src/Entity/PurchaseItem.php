<?php

namespace App\Entity;

use App\Purchasing\Entity\PurchaseItem as PurchasingPurchaseItem;

class_alias(PurchasingPurchaseItem::class, PurchaseItem::class);

/** @deprecated Use App\Purchasing\Entity\PurchaseItem */
class PurchaseItem extends PurchasingPurchaseItem
{
}
