<?php

namespace App\Role\Domain;

class RoleUpdateUseCase
{
    private RoleRepositoryInterface $repository;

    public function __construct(RoleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Role $role): void
    {
        $this->repository->update($role);
    }
}
