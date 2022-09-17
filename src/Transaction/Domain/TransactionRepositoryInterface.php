<?php

namespace App\Transaction\Domain;

interface TransactionRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Transaction $transaction): void;

    public function update(Transaction $transaction): void;

    public function delete(string $id): void;
}