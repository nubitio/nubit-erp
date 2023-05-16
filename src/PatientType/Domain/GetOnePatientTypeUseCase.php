<?php

namespace App\PatientType\Domain;

class GetOnePatientTypeUseCase
{
    private PatientTypeRepositoryInterface $repository;

    public function __construct(PatientTypeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?PatientType
    {
        return $this->repository->getOne($id);
    }
}
