<?php

namespace App\Invoice\Domain;

interface InvoiceRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function getOne(string $saleId): Invoice;

    public function create(string $saleId, string $localId): void;

    public function insert(Invoice $invoice): void;

    public function update(Invoice $invoice): void;

    public function delete(string $id): void;
}