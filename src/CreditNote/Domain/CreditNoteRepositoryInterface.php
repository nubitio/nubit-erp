<?php

namespace App\CreditNote\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface CreditNoteRepositoryInterface
{
    /**
     * @return PaginatorInterface<CreditNote>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?CreditNote;

    public function insert(CreditNote $creditNote): CreditNote;

    public function update(CreditNote $creditNote): CreditNote;

    public function delete(string $id): void;
}
