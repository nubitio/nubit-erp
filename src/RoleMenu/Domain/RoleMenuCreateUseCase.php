<?php

namespace App\RoleMenu\Domain;

class RoleMenuCreateUseCase
{
    private RoleMenuRepositoryInterface $repository;

    public function __construct(RoleMenuRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $roleId, array $roleMenuList): void
    {
        $this->repository->insertMultiple($roleId, $roleMenuList);
    }
}
