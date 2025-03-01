<?php

namespace App\Invoice\Domain;

class GetOneInvoiceBySaleIdUseCase
{
    public function __construct(private readonly InvoiceRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): ?Invoice
    {
        return $this->repository->getOneBySaleId($id);
    }
}
