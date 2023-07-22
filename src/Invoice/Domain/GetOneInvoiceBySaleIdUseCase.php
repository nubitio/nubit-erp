<?php

namespace App\Invoice\Domain;

class GetOneInvoiceBySaleIdUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Invoice
    {
        return $this->repository->getOneBySaleId($id);
    }
}
