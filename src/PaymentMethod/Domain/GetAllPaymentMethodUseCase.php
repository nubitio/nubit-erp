<?php

namespace App\PaymentMethod\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

class GetAllPaymentMethodUseCase
{
    private PaymentMethodRepositoryInterface $repository;

    public function __construct(PaymentMethodRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}