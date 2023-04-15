<?php

namespace App\Invoice\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Sale\Domain\Sale;

interface InvoiceRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Invoice>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $saleId): Invoice;

    public function create(Sale $sale): void;

    public function insert(Invoice $invoice): void;

    public function update(Invoice $invoice): void;

    public function delete(string $id): void;
}
