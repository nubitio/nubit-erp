<?php

namespace App\Employee\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface EmployeeRepositoryInterface
{
    /**
     * @return PaginatorInterface<Employee>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Employee;

    public function insert(Employee $employee): Employee;

    public function update(Employee $employee): Employee;

    public function delete(int $id): void;
}
