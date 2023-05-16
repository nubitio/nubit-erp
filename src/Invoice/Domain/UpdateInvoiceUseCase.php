<?php

namespace App\Invoice\Domain;

class UpdateInvoiceUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Invoice $invoice): Invoice
    {
        return $this->repository->update($invoice);
    }
}
