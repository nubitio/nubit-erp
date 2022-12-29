<?php

namespace App\Invoice\Domain;

class InvoiceGetAllUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
