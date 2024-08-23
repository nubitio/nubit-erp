<?php

namespace App\Cashbox\Domain;

interface CashboxRepositoryInterface
{
    /**
     * @param array<int , array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function getOne(string $id): Cashbox;

    public function getLast(string $localId, int $userId): ?Cashbox;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInfoByReceiptType(string $id): array;

    public function insert(Cashbox $cashbox): void;

    public function update(Cashbox $cashbox): void;

    public function delete(string $id): void;

    public function updateValues(string $localId, int $userId): void;
}
