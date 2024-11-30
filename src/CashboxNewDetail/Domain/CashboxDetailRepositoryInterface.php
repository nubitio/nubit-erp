<?php

namespace App\CashboxNewDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CashboxDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<CashboxDetail>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?CashboxDetail;

    public function insert(CashboxDetail $cashboxDetail): CashboxDetail;

    public function update(CashboxDetail $cashboxDetail): CashboxDetail;

    public function delete(int $id): void;
}
