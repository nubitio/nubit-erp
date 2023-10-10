<?php

namespace App\MilitaryPatient\Domain;

use App\Patient\Domain\GetOnePatientByCustomerIdUseCase;

readonly class GetOneMilitaryPatientByCustomerIdUseCase
{
    public function __construct(
        private MilitaryPatientRepositoryInterface $repository,
        private GetOnePatientByCustomerIdUseCase $getOnePatientByCustomerIdUseCase,
    ) {
    }

    public function __invoke(string $customerId): ?MilitaryPatient
    {
        $patient = $this->getOnePatientByCustomerIdUseCase->__invoke($customerId);

        return $this->repository->findOneByPatientId(intval($patient?->id));
    }
}
