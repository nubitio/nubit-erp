<?php

namespace App\Role\Domain;

class GetOneRoleUseCase
{
    private RoleRepositoryInterface $repository;

    public function __construct(RoleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Role
    {
        return $this->repository->getOne($id);
    }
}