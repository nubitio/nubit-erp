<?php

namespace App\VoucherType\Domain;

class VoucherTypeCreateUseCase
{
    private VoucherTypeRepositoryInterface $repository;

    public function __construct(VoucherTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherType $voucherType): void
    {
        $this->repository->insert($voucherType);
    }
}
