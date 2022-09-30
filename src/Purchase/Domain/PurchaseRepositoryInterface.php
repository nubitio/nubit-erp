<?php

namespace App\Purchase\Domain;

interface PurchaseRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Purchase $purchase): void;

    public function update(Purchase $purchase): void;

    public function delete(string $id): void;
}