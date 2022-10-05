<?php

namespace App\Role\Domain;

class RoleCreateUseCase
{
    private RoleRepositoryInterface $repository;

    public function __construct(RoleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Role $role): void
    {
        $this->repository->insert($role);
    }
}