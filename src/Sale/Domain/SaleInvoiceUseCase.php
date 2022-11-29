<?php

namespace App\Sale\Domain;

use App\Billing\Domain\InvoiceRepositoryInterface;

class SaleInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface       $invoiceRepository,
        private readonly SaleRepositoryInterface $saleRepository,
    ) {
    }

    public function __invoke(string $saleId)
    {
        $receipt = $this->saleRepository->getOneById($saleId);

    }
}