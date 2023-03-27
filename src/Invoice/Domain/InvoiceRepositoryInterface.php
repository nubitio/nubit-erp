<?php

namespace App\Invoice\Domain;

use App\Sale\Domain\Sale;

interface InvoiceRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function getOne(string $saleId): Invoice;

    public function create(Sale $sale): void;

    public function insert(Invoice $invoice): void;

    public function update(Invoice $invoice): void;

    public function delete(string $id): void;
}
