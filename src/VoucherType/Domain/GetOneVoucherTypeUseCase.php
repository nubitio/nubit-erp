<?php

namespace App\VoucherType\Domain;

class GetOneVoucherTypeUseCase
{
    private VoucherTypeRepositoryInterface $repository;

    public function __construct(VoucherTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?VoucherType
    {
        return $this->repository->getOne($id);
    }
}
