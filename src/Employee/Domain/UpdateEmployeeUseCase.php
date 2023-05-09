<?php

namespace App\Employee\Domain;

class UpdateEmployeeUseCase
{
    private EmployeeRepositoryInterface $repository;

    public function __construct(EmployeeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Employee $employee): Employee
    {
        return $this->repository->update($employee);
    }
}