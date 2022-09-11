<?php

namespace App\VoucherType\Domain;

class VoucherTypeGetAllUseCase
{
    private VoucherTypeRepositoryInterface $repository;

    public function __construct(VoucherTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}