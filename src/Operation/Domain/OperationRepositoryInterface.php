<?php

namespace App\Operation\Domain;

interface OperationRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Operation $operation): void;

    public function update(Operation $operation): void;

    public function delete(string $id): void;
}