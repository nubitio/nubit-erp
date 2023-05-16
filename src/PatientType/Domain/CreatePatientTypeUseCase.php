<?php

namespace App\PatientType\Domain;

class CreatePatientTypeUseCase
{
    private PatientTypeRepositoryInterface $repository;

    public function __construct(PatientTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(PatientType $patientType): PatientType
    {
        return $this->repository->insert($patientType);
    }
}
