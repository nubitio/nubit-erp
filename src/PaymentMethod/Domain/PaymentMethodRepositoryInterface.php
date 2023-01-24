<?php

namespace App\PaymentMethod\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface PaymentMethodRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?PaymentMethod;

    public function insert(PaymentMethod $paymentMethod): PaymentMethod;

    public function update(PaymentMethod $paymentMethod): PaymentMethod;

    public function delete(string $id): void;
}