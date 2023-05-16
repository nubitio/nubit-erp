<?php

namespace App\Patient\Domain;

class GetOnePatientUseCase
{
    private PatientRepositoryInterface $repository;

    public function __construct(PatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Patient
    {
        return $this->repository->getOne($id);
    }
}
