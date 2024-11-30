<?php

namespace App\VoucherType\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface VoucherTypeRepositoryInterface
{
    /**
     * @return PaginatorInterface<VoucherType>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?VoucherType;

    public function insert(VoucherType $voucherType): VoucherType;

    public function update(VoucherType $voucherType): VoucherType;

    public function delete(int $id): void;
}
