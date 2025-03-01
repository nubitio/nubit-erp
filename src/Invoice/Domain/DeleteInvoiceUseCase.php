<?php

namespace App\Invoice\Domain;

class DeleteInvoiceUseCase
{
    public function __construct(private readonly InvoiceRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}
