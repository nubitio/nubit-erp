<?php

namespace App\Invoice\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Entity\SaleEntity;

interface InvoiceRepositoryInterface
{
    /**
     * @return PaginatorInterface<Invoice>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $invoiceId): Invoice;

    public function getOneBySaleId(string $saleId): Invoice;

    public function create(SaleEntity $sale): void;

    public function insert(Invoice $invoice): Invoice;

    public function update(Invoice $invoice): Invoice;

    public function delete(string $id): void;

    public function getVoucher(string $saleId): Voucher;
}
