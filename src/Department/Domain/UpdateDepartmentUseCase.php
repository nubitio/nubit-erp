<?php

namespace App\Department\Domain;

class UpdateDepartmentUseCase
{
    private DepartmentRepositoryInterface $repository;

    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Department $department): Department
    {
        return $this->repository->update($department);
    }
}
