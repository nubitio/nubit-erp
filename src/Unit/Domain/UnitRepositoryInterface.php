<?php

namespace App\Unit\Domain;

interface UnitRepositoryInterface
{
    /**
     * @param array<int, mixed>|null $filter
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function insert(Unit $unit): void;

    public function update(Unit $unit): void;

    public function delete(string $id): void;
}
