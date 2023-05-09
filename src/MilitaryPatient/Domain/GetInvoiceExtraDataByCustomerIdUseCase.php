<?php

namespace App\MilitaryPatient\Domain;

use App\Sale\Domain\InvoiceExtraData;

class GetInvoiceExtraDataByCustomerIdUseCase
{
    private MilitaryPatientRepositoryInterface $repository;

    public function __construct(MilitaryPatientRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $customerId, string $saleId): ?InvoiceExtraData
    {
        return $this->repository->getInvoiceExtraDataByCustomerId($customerId, $saleId);
    }
}