<?php

namespace App\Patient\Domain;

class GetOnePatientByCustomerIdUseCase
{
    private PatientRepositoryInterface $repository;

    public function __construct(PatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $customerId): ?Patient
    {
        return $this->repository->getOneByCustomerId($customerId);
    }
}
