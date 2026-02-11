<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use Greenter\Model\Response\StatusCdrResult;

readonly class BillConsultUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    public function __invoke(string $taxDocumentId): StatusCdrResult
    {
        $bill = $this->billCreateUseCase->__invoke($taxDocumentId);

        return $this->billRepository->getStatus($bill);
    }
}
