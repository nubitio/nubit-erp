<?php

namespace App\Role\Domain;

interface RoleRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Role $role): void;

    public function update(Role $role): void;

    public function delete(string $id): void;
}