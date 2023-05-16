<?php

namespace App\Department\Domain;

class CreateDepartmentUseCase
{
    private DepartmentRepositoryInterface $repository;

    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Department $department): Department
    {
        return $this->repository->insert($department);
    }
}
