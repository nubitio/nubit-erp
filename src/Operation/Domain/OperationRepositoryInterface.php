<?php

namespace App\Operation\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface OperationRepositoryInterface
{
    /**
     * @return PaginatorInterface<Operation>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Operation;

    public function insert(Operation $operation): Operation;

    public function update(Operation $operation): Operation;

    public function delete(string $id): void;
}
