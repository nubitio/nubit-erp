<?php

namespace App\MilitaryPatient\Domain;

class CreateMilitaryPatientUseCase
{
    private MilitaryPatientRepositoryInterface $repository;

    public function __construct(MilitaryPatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(MilitaryPatient $militaryPatient): MilitaryPatient
    {
        return $this->repository->insert($militaryPatient);
    }
}