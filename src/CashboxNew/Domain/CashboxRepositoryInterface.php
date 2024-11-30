<?php

namespace App\CashboxNew\Domain;

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

    public function getLast(string $localId, int $userId): ?Cashbox;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInfoByReceiptType(string $id): array;

    public function updateValues(string $localId, int $userId): void;
}
