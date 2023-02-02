<?php

namespace App\VoucherType\Domain;

class UpdateVoucherTypeUseCase
{
    private VoucherTypeRepositoryInterface $repository;

    public function __construct(VoucherTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherType $voucherType): VoucherType
    {
        return $this->repository->update($voucherType);
    }
}