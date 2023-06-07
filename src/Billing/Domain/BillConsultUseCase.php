<?php

namespace App\Billing\Domain;

use Greenter\Model\Response\StatusCdrResult;

readonly class BillConsultUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    public function __invoke(string $saleId): StatusCdrResult
    {
        $bill = $this->billCreateUseCase->__invoke($saleId);

        return $this->billRepository->getStatus($bill);
    }
}
