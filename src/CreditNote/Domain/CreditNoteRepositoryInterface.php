<?php

namespace App\CreditNote\Domain;

interface CreditNoteRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(CreditNote $creditNote): void;

    public function update(CreditNote $creditNote): void;

    public function delete(string $id): void;
}