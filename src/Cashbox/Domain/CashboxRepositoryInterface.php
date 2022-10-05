<?php

namespace App\Cashbox\Domain;

interface CashboxRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Cashbox $cashbox): void;

    public function update(Cashbox $cashbox): void;

    public function delete(string $id): void;
}