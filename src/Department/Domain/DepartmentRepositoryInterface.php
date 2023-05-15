<?php

namespace App\Department\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface DepartmentRepositoryInterface
{
    /**
     * @return PaginatorInterface<Department>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Department;

    public function insert(Department $department): Department;

    public function update(Department $department): Department;

    public function delete(int $id): void;
}
