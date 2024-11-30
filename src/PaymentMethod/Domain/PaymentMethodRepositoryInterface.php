<?php

namespace App\PaymentMethod\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface PaymentMethodRepositoryInterface
{
    /**
     * @return PaginatorInterface<PaymentMethod>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?PaymentMethod;

    public function insert(PaymentMethod $paymentMethod): PaymentMethod;

    public function update(PaymentMethod $paymentMethod): PaymentMethod;

    public function delete(int $id): void;
}
