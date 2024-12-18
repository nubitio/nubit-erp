<?php

namespace App\Cashbox\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CashboxRepositoryInterface
{
    /**
     * @return PaginatorInterface<Cashbox>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): Cashbox;

    public function insert(Cashbox $cashbox): Cashbox;

    public function update(Cashbox $cashbox): Cashbox;

    public function delete(string $id): void;

    public function getLast(string $localId): ?Cashbox;

    public function updateValues(string $localId, int $userId): void;

    public function getTotalBalance(string $cashboxId): float;
}
