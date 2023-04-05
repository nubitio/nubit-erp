<?php

namespace App\RoleMenu\Domain;

interface RoleMenuRepositoryInterface
{
    /**
     * @return array<int, mixed>
     */
    public function getAllByRole(int $roleId): array;

    /**
     * @param RoleMenu[] $roleMenuList
     */
    public function insertMultiple(int $roleId, array $roleMenuList): void;
}
