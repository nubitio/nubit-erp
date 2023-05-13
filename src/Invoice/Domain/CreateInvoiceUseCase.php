<?php

namespace App\Invoice\Domain;

class CreateInvoiceUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Invoice $invoice): Invoice
    {
        return $this->repository->insert($invoice);
    }
}
