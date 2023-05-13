<?php

namespace App\Role\Domain;

class UpdateRoleUseCase
{
    private RoleRepositoryInterface $repository;

    public function __construct(RoleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Role $role): Role
    {
        return $this->repository->update($role);
    }
}
