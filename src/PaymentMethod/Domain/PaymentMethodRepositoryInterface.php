<?php

namespace App\PaymentMethod\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface PaymentMethodRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<PaymentMethod>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?PaymentMethod;

    public function insert(PaymentMethod $paymentMethod): PaymentMethod;

    public function update(PaymentMethod $paymentMethod): PaymentMethod;

    public function delete(string $id): void;
}