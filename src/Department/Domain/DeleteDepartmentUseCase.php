<?php

namespace App\Department\Domain;

class DeleteDepartmentUseCase
{
    private DepartmentRepositoryInterface $repository;

    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
