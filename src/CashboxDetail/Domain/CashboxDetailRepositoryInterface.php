<?php

namespace App\CashboxDetail\Domain;

interface CashboxDetailRepositoryInterface
{
    /**
     * @param array<int, array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function getAll(array $filter = null): array;

    public function insert(CashboxDetail $cashboxDetail): void;

    public function update(CashboxDetail $cashboxDetail): void;

    public function delete(int $id): void;
}
