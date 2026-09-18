<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical\Support;

use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;

/** Seeded tenant slice used by core operational kernel tests. */
final readonly class CoreSaleWorld
{
    public function __construct(
        public User $user,
        public Branch $branch,
        public Warehouse $warehouse,
        public Customer $customer,
        public Product $product,
        public PaymentMethod $cashMethod,
        public PaymentMethod $creditMethod,
        public CashRegister $cashRegister,
        public CashSession $cashSession,
        public string $initialStock,
    ) {}
}
