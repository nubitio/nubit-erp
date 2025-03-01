<?php

namespace App\Invoice\Domain;

class UpdateInvoiceUseCase
{
    public function __construct(private readonly InvoiceRepositoryInterface $repository)
    {
    }

    public function __invoke(Invoice $invoice): Invoice
    {
        return $this->repository->update($invoice);
    }
}
