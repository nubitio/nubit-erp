<?php

namespace App\Customer\Domain;

interface CustomerRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Customer $customer): void;

    public function update(Customer $customer): void;

    public function delete(string $id): void;
}