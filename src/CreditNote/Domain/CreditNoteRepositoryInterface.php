<?php

namespace App\CreditNote\Domain;

interface CreditNoteRepositoryInterface
{
    /**
     * @param array<int, array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function insert(CreditNote $creditNote): void;

    public function update(CreditNote $creditNote): void;

    public function delete(string $id): void;
}
