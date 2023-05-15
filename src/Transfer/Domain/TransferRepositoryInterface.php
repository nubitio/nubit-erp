<?php

namespace App\Transfer\Domain;

interface TransferRepositoryInterface
{
    /**
     * @param array<int, array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function insert(Transfer $transfer): void;

    public function update(Transfer $transfer): void;

    public function delete(string $id): void;
}
