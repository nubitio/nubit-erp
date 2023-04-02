<?php

namespace App\Customer\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface CustomerRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Customer>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Customer;

    public function insert(Customer $customer): Customer;

    public function update(Customer $customer): Customer;

    public function delete(string $id): void;
}