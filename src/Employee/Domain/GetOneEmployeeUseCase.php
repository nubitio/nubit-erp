<?php

namespace App\Employee\Domain;

class GetOneEmployeeUseCase
{
    private EmployeeRepositoryInterface $repository;

    public function __construct(EmployeeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Employee
    {
        return $this->repository->getOne($id);
    }
}
