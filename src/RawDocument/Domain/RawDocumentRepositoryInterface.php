<?php

namespace App\RawDocument\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface RawDocumentRepositoryInterface
{
    /**
     * @return PaginatorInterface<RawDocument>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): RawDocument;

    public function insert(RawDocument $rawDocument): RawDocument;

    public function update(RawDocument $rawDocument): RawDocument;

    public function delete(int $id): void;
}
