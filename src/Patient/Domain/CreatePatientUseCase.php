<?php

namespace App\Patient\Domain;

class CreatePatientUseCase
{
    private PatientRepositoryInterface $repository;

    public function __construct(PatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Patient $patient): Patient
    {
        return $this->repository->insert($patient);
    }
}