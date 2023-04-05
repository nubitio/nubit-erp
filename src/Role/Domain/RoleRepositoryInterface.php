<?php

namespace App\Role\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface RoleRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Role>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Role;

    public function insert(Role $role): Role;

    public function update(Role $role): Role;

    public function delete(int $id): void;
}