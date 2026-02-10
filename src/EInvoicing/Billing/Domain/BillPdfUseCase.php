<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

readonly class BillPdfUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    public function __invoke(string $taxDocumentId): FileResponse
    {
        $bill = $this->billCreateUseCase->__invoke($taxDocumentId);
        $pdf = $this->billRepository->getPdf($bill);

        return new FileResponse($pdf, $bill->getName() . '.pdf');
    }
}
