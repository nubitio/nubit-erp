<?php

namespace App\Branch\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface BranchRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?Branch;

    public function insert(Branch $branch): Branch;

    public function update(Branch $branch): Branch;

    public function delete(string $id): void;
}