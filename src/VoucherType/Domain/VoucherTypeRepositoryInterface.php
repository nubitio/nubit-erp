<?php

namespace App\VoucherType\Domain;

interface VoucherTypeRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(VoucherType $vouchertype): void;

    public function update(VoucherType $vouchertype): void;

    public function delete(string $id): void;
}