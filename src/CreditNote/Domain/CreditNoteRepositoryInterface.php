<?php

namespace App\CreditNote\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CreditNoteRepositoryInterface
{
    /**
     * @return PaginatorInterface<CreditNote>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?CreditNote;

    public function insert(CreditNote $creditNote): CreditNote;

    public function update(CreditNote $creditNote): CreditNote;

    public function delete(string $id): void;
}
