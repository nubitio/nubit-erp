<?php

namespace App\Unit\Domain;

interface UnitRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Unit $unit): void;

    public function update(Unit $unit): void;

    public function delete(string $id): void;
}