<?php

namespace App\CashboxDetail\Domain;

interface CashboxDetailRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(CashboxDetail $cashboxDetail): void;

    public function update(CashboxDetail $cashboxDetail): void;

    public function delete(int $id): void;
}