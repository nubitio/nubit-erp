<?php

namespace App\Role\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface RoleRepositoryInterface
{
    /**
     * @return PaginatorInterface<Role>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Role;

    public function insert(Role $role): Role;

    public function update(Role $role): Role;

    public function delete(int $id): void;
}
