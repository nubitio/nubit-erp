<?php

namespace App\Customer\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface CustomerRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?Customer;

    public function insert(Customer $customer): Customer;

    public function update(Customer $customer): Customer;

    public function delete(string $id): void;
}