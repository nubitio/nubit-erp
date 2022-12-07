<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;

class BillSendUseCase
{
    public function __construct(
        private readonly BillCreateUseCase       $billCreateUseCase,
        private readonly BillRepositoryInterface $billRepository,
    )
    {
    }

    public function __invoke(string $saleId): DocumentResponse
    {
        $bill = $this->billCreateUseCase->__invoke($saleId);
        $response = $this->billRepository->sendBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}