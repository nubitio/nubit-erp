<?php

namespace App\VoucherType\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface VoucherTypeRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?VoucherType;

    public function insert(VoucherType $voucherType): VoucherType;

    public function update(VoucherType $voucherType): VoucherType;

    public function delete(string $id): void;
}