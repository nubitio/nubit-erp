<?php

namespace App\PaymentMethod\Domain;

class GetOnePaymentMethodUseCase
{
    private PaymentMethodRepositoryInterface $repository;

    public function __construct(PaymentMethodRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?PaymentMethod
    {
        return $this->repository->getOne($id);
    }
}