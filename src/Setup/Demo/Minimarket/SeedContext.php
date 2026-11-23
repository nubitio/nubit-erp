<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\CashRegister\Entity\CashRegister;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;

/** What the minimarket demo writes into: the branch it sells from and the records the story refers to by key. */
final readonly class SeedContext
{
    /**
     * @param array<string, Product> $products by code
     * @param array<string, Customer> $customers by story key
     * @param array<string, User> $cashiers by story key
     * @param array<string, PaymentMethod> $paymentMethods by name
     */
    public function __construct(
        public Branch $branch,
        public Warehouse $warehouse,
        public CashRegister $cashRegister,
        public array $products,
        public array $customers,
        public array $cashiers,
        public array $paymentMethods,
    ) {}
}
