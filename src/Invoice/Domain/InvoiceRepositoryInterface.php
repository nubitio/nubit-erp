<?php

namespace App\Invoice\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Sale\Domain\Sale;

interface InvoiceRepositoryInterface
{
    /**
     * @return PaginatorInterface<Invoice>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $invoiceId): Invoice;

    public function getOneBySaleId(string $saleId): Invoice;

    public function create(Sale $sale): void;

    public function insert(Invoice $invoice): Invoice;

    public function update(Invoice $invoice): Invoice;

    public function delete(string $id): void;

    public function getVoucher(string $saleId): Voucher;
}
