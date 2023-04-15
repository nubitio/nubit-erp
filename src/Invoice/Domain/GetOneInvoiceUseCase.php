<?php

namespace App\Invoice\Domain;

class GetOneInvoiceUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Invoice
    {
        return $this->repository->getOne($id);
    }
}