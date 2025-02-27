<?php

namespace App\Billing\Domain;

readonly class BillPdfUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    public function __invoke(string $saleId): FileResponse
    {
        $bill = $this->billCreateUseCase->__invoke($saleId);
        $pdf = $this->billRepository->getPdf($bill);

        return new FileResponse($pdf, $bill->getName() . '.pdf');
    }
}
