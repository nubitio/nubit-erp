<?php

namespace App\RoleMenu\Domain;

interface RoleMenuRepositoryInterface
{
    public function getAllByRole(int $roleId): array;

    public function insertMultiple(int $roleId, array $roleMenuList): void;
}
