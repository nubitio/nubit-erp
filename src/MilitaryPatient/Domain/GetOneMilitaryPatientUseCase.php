<?php

namespace App\MilitaryPatient\Domain;

class GetOneMilitaryPatientUseCase
{
    private MilitaryPatientRepositoryInterface $repository;

    public function __construct(MilitaryPatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?MilitaryPatient
    {
        return $this->repository->getOne($id);
    }
}
