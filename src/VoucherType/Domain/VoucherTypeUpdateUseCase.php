<?php

namespace App\VoucherType\Domain;

class VoucherTypeUpdateUseCase
{
    private VoucherTypeRepositoryInterface $repository;

    public function __construct(VoucherTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherType $vouchertype): void
    {
        $this->repository->update($vouchertype);
    }
}