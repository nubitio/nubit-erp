<?php

namespace App\PaymentMethod\Domain;

class CreatePaymentMethodUseCase
{
    private PaymentMethodRepositoryInterface $repository;

    public function __construct(PaymentMethodRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $this->repository->insert($paymentMethod);
    }
}