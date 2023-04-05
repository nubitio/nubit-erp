<?php

namespace App\Operation\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface OperationRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Operation>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Operation;

    public function insert(Operation $operation): Operation;

    public function update(Operation $operation): Operation;

    public function delete(string $id): void;
}