<?php

namespace App\CashboxDetail\Domain;

interface CashboxDetailRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(CashboxDetail $cashboxDetail): void;

    public function update(CashboxDetail $cashboxDetail): void;

    public function delete(string $id): void;
}