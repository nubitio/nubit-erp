<?php

namespace App\MilitaryPatient\Domain;

class GetOneMilitaryPatientByCustomerIdUseCase
{
    private MilitaryPatientRepositoryInterface $repository;

    public function __construct(MilitaryPatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?MilitaryPatient
    {
        return $this->repository->getOneByCustomerId($id);
    }
}