<?php

namespace App\Branch\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface BranchRepositoryInterface
{
    /**
     * @return PaginatorInterface<Branch>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Branch;

    public function insert(Branch $branch): Branch;

    public function update(Branch $branch): Branch;

    public function delete(string $id): void;
}
