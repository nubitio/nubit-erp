<?php

namespace App\Invoice\Domain;

class GetOneVoucherUseCase
{
    private InvoiceRepositoryInterface $repository;

    public function __construct(InvoiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): Voucher
    {
        return $this->repository->getVoucher($id);
    }
}
