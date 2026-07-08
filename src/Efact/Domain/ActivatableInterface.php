<?php

declare(strict_types=1);

namespace App\Efact\Domain;

/**
 * Implemented by master-data entities that carry an is_active toggle
 * (Customer, Supplier, Warehouse, Category, VoucherSeries, VoucherType,
 * UnitOfMeasure, CashRegister, Branch, ...). Lets application services guard
 * against wiring new transactions to a disabled reference without needing to
 * know the concrete entity type — see {@see ActiveReferenceGuard}.
 */
interface ActivatableInterface
{
    public function getIsActive(): bool;
}
