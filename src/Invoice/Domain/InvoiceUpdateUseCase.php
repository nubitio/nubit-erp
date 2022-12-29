<?php

namespace App\Invoice\Domain;

class InvoiceUpdateUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Invoice $invoice): void
    {
        $this->repository->update($invoice);
    }
}
