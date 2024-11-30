<?php

namespace App\Customer\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CustomerRepositoryInterface
{
    /**
     * @return PaginatorInterface<Customer>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Customer;

    public function insert(Customer $customer): Customer;

    public function update(Customer $customer): Customer;

    public function delete(string $id): void;
}
