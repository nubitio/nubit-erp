<?php

namespace App\VoucherType\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface VoucherTypeRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<VoucherType>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?VoucherType;

    public function insert(VoucherType $voucherType): VoucherType;

    public function update(VoucherType $voucherType): VoucherType;

    public function delete(int $id): void;
}