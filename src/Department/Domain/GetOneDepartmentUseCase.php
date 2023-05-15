<?php

namespace App\Department\Domain;

class GetOneDepartmentUseCase
{
    private DepartmentRepositoryInterface $repository;

    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Department
    {
        return $this->repository->getOne($id);
    }
}
