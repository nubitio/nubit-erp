<?php

namespace App\PatientType\Domain;

class UpdatePatientTypeUseCase
{
    private PatientTypeRepositoryInterface $repository;

    public function __construct(PatientTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(PatientType $patientType): PatientType
    {
        return $this->repository->update($patientType);
    }
}