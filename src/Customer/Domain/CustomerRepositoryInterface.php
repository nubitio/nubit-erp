<?php

namespace App\Customer\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface CustomerRepositoryInterface
{
    /**
     * @return PaginatorInterface<Customer>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Customer;

    public function insert(Customer $customer): Customer;

    public function update(Customer $customer): Customer;

    public function delete(int $id): void;
}
