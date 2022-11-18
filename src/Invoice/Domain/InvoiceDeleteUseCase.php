<?php

namespace App\Invoice\Domain;

class InvoiceDeleteUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}