<?php

namespace App\VoucherType\Domain;

interface VoucherTypeRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(VoucherType $voucherType): void;

    public function update(VoucherType $voucherType): void;

    public function delete(string $id): void;
}