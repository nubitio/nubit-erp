<?php

namespace App\PatientType\Domain;

class DeletePatientTypeUseCase
{
    private PatientTypeRepositoryInterface $repository;

    public function __construct(PatientTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
