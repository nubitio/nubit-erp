<?php

namespace App\Department\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface DepartmentRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Department>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Department;

    public function insert(Department $department): Department;

    public function update(Department $department): Department;

    public function delete(int $id): void;
}