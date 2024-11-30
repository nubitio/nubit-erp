<?php

namespace App\Branch\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface BranchRepositoryInterface
{
    /**
     * @return PaginatorInterface<Branch>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Branch;

    public function insert(Branch $branch): Branch;

    public function update(Branch $branch): Branch;

    public function delete(string $id): void;
}
