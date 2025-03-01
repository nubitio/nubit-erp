<?php

namespace App\Invoice\Domain;

class GetOneVoucherUseCase
{
    public function __construct(private readonly InvoiceRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): Voucher
    {
        return $this->repository->getVoucher($id);
    }
}
